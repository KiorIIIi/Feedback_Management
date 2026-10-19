<?php
namespace Tests\Feature;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class PortalWorkflowTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();$this->seed();}
 private function user(string $role):User{return User::where('role',$role)->firstOrFail();}
 private function payload():array{return ['service_type_id'=>DB::table('service_types')->first()->id,'requesting_office_id'=>$this->user('requester')->office_id,'subject'=>'New laboratory connectivity request','description'=>'The laboratory Wi-Fi connection is unstable and needs inspection.','location'=>'Engineering laboratory','urgency'=>'Medium','impact'=>'Entire office'];}
 public function test_private_access_and_internal_visibility():void {
  $requester=$this->user('requester');$r=ServiceRequest::where('requester_id',$requester->id)->firstOrFail();
  $this->getJson('/api/requests')->assertUnauthorized();
  $this->actingAs($requester)->getJson('/api/requests/'.$r->id)->assertOk()->assertDontSee('Demo internal note');
  $other=User::where('role','requester')->where('id','!=',$requester->id)->firstOrFail();
  $this->actingAs($other)->getJson('/api/requests/'.$r->id)->assertNotFound();
  $admin=$this->user('admin');$this->actingAs($admin)->getJson('/api/requests/'.$r->id)->assertOk()->assertSee('Demo internal note');
  $foreignOffice=DB::table('offices')->where('office_code','Science')->value('id');$admin->office_id=$foreignOffice;$admin->save();
  $this->actingAs($admin)->getJson('/api/requests/'.$r->id)->assertNotFound();
 }
 public function test_full_lifecycle_clarification_and_single_evaluation():void {
  $owner=$this->user('requester');$admin=$this->user('admin');$personnel=$this->user('personnel');
  $r=$this->actingAs($owner)->postJson('/api/requests',$this->payload())->assertCreated()->json();$id=$r['id'];$number=$r['job_number'];
  $this->assertNull($r['priority']);
  $r=$this->actingAs($admin)->patchJson('/api/requests/'.$id,['action'=>'clarify','expected_version'=>1,'message'=>'Which room is affected?'])->assertOk()->json();
  $r=$this->actingAs($owner)->patchJson('/api/requests/'.$id,['action'=>'reply','expected_version'=>$r['record_version'],'message'=>'Room 204 is affected.'])->assertOk()->json();
  $this->assertSame($number,$r['job_number']);$this->assertSame($this->payload()['description'],$r['description']);$this->assertDatabaseCount('request_revisions',1);
  $r=$this->actingAs($admin)->patchJson('/api/requests/'.$id,['action'=>'manage','expected_version'=>$r['record_version'],'personnel_id'=>$personnel->id,'priority'=>'High','reason'=>'Demo reviewed priority','status'=>'Assigned'])->assertOk()->json();
  $r=$this->actingAs($personnel)->patchJson('/api/requests/'.$id,['action'=>'manage','expected_version'=>$r['record_version'],'status'=>'In Progress','message'=>'Inspected the access point.'])->assertOk()->json();
  $this->actingAs($personnel)->patchJson('/api/requests/'.$id,['action'=>'manage','expected_version'=>$r['record_version'],'status'=>'Completed','message'=>'Replaced the cable.'])->assertUnprocessable()->assertJsonValidationErrors('outcome');
  $r=$this->actingAs($personnel)->patchJson('/api/requests/'.$id,['action'=>'manage','expected_version'=>$r['record_version'],'status'=>'Completed','message'=>'Replaced the cable.','outcome'=>'Connectivity restored and validated.'])->assertOk()->json();
  $this->assertSame('Completed',$r['status']);$this->assertNotNull($r['completed_at']);
  $this->actingAs($owner)->postJson('/api/requests/'.$id.'/evaluation',['rating'=>4,'remarks'=>'Helpful service.'])->assertCreated();
  $this->actingAs($owner)->postJson('/api/requests/'.$id.'/evaluation',['rating'=>5])->assertUnprocessable();
  $this->assertDatabaseHas('service_evaluations',['request_id'=>$id,'rating'=>4]);
 }
 public function test_stale_version_rejects_all_changes_and_rolls_back_assignment():void {
  $r=ServiceRequest::where('status','Pending')->firstOrFail();$admin=$this->user('admin');$personnel=$this->user('personnel');$count=DB::table('assignments')->count();
  $this->actingAs($admin)->patchJson('/api/requests/'.$r->id,['action'=>'manage','expected_version'=>999,'personnel_id'=>$personnel->id,'status'=>'Assigned'])->assertConflict();
  $this->assertDatabaseCount('assignments',$count);
  $this->actingAs($admin)->patchJson('/api/requests/'.$r->id,['action'=>'manage','expected_version'=>1,'personnel_id'=>$personnel->id,'status'=>'Completed','message'=>'Invalid skip','outcome'=>'Cannot skip workflow'])->assertUnprocessable();
  $this->assertDatabaseCount('assignments',$count);$this->assertSame(1,$r->fresh()->record_version);
 }
 public function test_personnel_cannot_edit_unassigned_records_or_priority():void {
  $personnel=$this->user('personnel');$pending=ServiceRequest::where('status','Pending')->firstOrFail();
  $this->actingAs($personnel)->getJson('/api/requests/'.$pending->id)->assertNotFound();
  $assigned=ServiceRequest::whereIn('id',DB::table('assignments')->where('personnel_id',$personnel->id)->whereNull('ended_at')->select('service_request_id'))->firstOrFail();
  $this->patchJson('/api/requests/'.$assigned->id,['action'=>'manage','expected_version'=>1,'priority'=>'Low','reason'=>'Not permitted'])->assertForbidden();
 }
 public function test_public_feedback_does_not_consume_a_verified_rating():void {
  $r=ServiceRequest::where('status','Completed')->firstOrFail();
  $this->postJson('/api/feedback',['category'=>'Suggestion','subject'=>'Improve campus guidance','message'=>'Please publish clearer service guidance for new students.','unverified_job_number'=>$r->job_number,'rating'=>4])->assertCreated();
  $this->assertDatabaseCount('service_evaluations',0);$this->assertDatabaseHas('general_feedback',['unverified_job_number'=>$r->job_number]);
  $this->getJson('/api/requests/'.$r->id)->assertUnauthorized();
 }
 public function test_requester_cannot_mutate_management_fields_or_evaluate_others():void {
  $owner=$this->user('requester');$r=ServiceRequest::where('requester_id',$owner->id)->where('status','!=','Completed')->firstOrFail();
  $this->actingAs($owner)->patchJson('/api/requests/'.$r->id,['action'=>'manage','expected_version'=>1,'priority'=>'Critical','status'=>'Completed'])->assertForbidden();
  $other=User::where('role','requester')->where('id','!=',$owner->id)->firstOrFail();$completed=ServiceRequest::where('requester_id',$owner->id)->where('status','Completed')->firstOrFail();
  $this->actingAs($other)->postJson('/api/requests/'.$completed->id.'/evaluation',['rating'=>5])->assertNotFound();
 }
 public function test_export_is_private_scoped_and_downloadable_after_success():void {
  Storage::fake('local');$admin=$this->user('admin');
  $job=$this->actingAs($admin)->postJson('/api/reports',['days'=>30])->assertAccepted()->json();
  // afterCommit dispatches after test transaction; execute explicitly here.
  app()->call([new \App\Jobs\GenerateReport($job['id']),'handle']);
  $this->getJson('/api/reports/'.$job['id'])->assertOk()->assertJsonPath('status','completed');
  $this->get('/api/reports/'.$job['id'].'/download')->assertOk();
  $this->actingAs($this->user('requester'))->getJson('/api/reports/'.$job['id'])->assertNotFound();
  $this->postJson('/api/reports',['days'=>30])->assertForbidden();
  $admin->office_id=DB::table('offices')->where('office_code','Science')->value('id');$admin->save();
  $this->actingAs($admin)->getJson('/api/reports/'.$job['id'].'/download')->assertForbidden();
 }
 public function test_cross_origin_writes_are_rejected():void {
  $this->withHeaders(['Origin'=>'https://untrusted.example','Sec-Fetch-Site'=>'cross-site'])->postJson('/api/feedback',['category'=>'Other','subject'=>'Test','message'=>'Cross-site submission'])->assertForbidden();
 }
 public function test_requester_dashboard_counts_all_owned_records_and_only_unrated_completed_jobs():void {
  $owner=$this->user('requester');
  $completed=ServiceRequest::where('requester_id',$owner->id)->where('status','Completed')->firstOrFail();
  $completed->created_at=now()->subYears(2);$completed->save();
  $owned=ServiceRequest::where('requester_id',$owner->id);
  $this->actingAs($owner)->getJson('/api/requester-dashboard')->assertOk()
   ->assertJsonPath('counts.total',(clone $owned)->count())
   ->assertJsonPath('counts.awaiting_rating',2)
   ->assertJsonCount(4,'recent')->assertDontSee('Demo internal note');
  $this->postJson('/api/requests/'.$completed->id.'/evaluation',['rating'=>4])->assertCreated();
  $this->getJson('/api/requester-dashboard')->assertOk()->assertJsonPath('counts.awaiting_rating',1);
  $this->actingAs($this->user('admin'))->getJson('/api/requester-dashboard')->assertForbidden();
 }
}
