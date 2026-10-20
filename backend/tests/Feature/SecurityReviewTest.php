<?php
namespace Tests\Feature;

use App\Jobs\GenerateReport;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestEnvironmentGuard;
use Tests\TestCase;

class SecurityReviewTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();config(['hashing.bcrypt.rounds'=>4]);$this->seed();}
 private function user(string $role):User{return User::where('role',$role)->firstOrFail();}
 private function assigned():ServiceRequest{return ServiceRequest::whereIn('id',DB::table('assignments')->where('personnel_id',$this->user('personnel')->id)->whereNull('ended_at')->select('service_request_id'))->firstOrFail();}
 private function feedback():array{return ['category'=>'Other','subject'=>'Security fixture','message'=>'Synthetic feedback for isolated security tests.'];}

 public function test_personnel_cannot_read_evaluations_through_record_endpoints():void {
  $r=ServiceRequest::where('status','Completed')->firstOrFail();
  DB::table('service_evaluations')->insert(['request_id'=>$r->id,'rating'=>2,'remarks'=>'Private evaluation remarks','created_at'=>now()]);
  $p=User::findOrFail(DB::table('assignments')->where('service_request_id',$r->id)->orderByDesc('id')->value('personnel_id'));
  $this->actingAs($p)->getJson('/api/requests/'.$r->id)->assertOk()->assertJsonPath('evaluation',null)->assertDontSee('Private evaluation remarks');
  $this->getJson('/api/requests')->assertOk()->assertDontSee('Private evaluation remarks');
  $this->actingAs(User::findOrFail($r->requester_id))->getJson('/api/requests/'.$r->id)->assertOk()->assertJsonPath('evaluation.rating',2);
  $this->actingAs($this->user('admin'))->getJson('/api/requests/'.$r->id)->assertOk()->assertJsonPath('evaluation.rating',2);
 }
 public function test_personnel_cannot_bypass_review_after_clarification():void {
  $r=$this->assigned();$admin=$this->user('admin');
  $r=$this->actingAs($admin)->patchJson('/api/requests/'.$r->id,['action'=>'clarify','message'=>'Please confirm the room.','expected_version'=>$r->record_version])->assertOk()->json();
  $this->actingAs($this->user('personnel'))->patchJson('/api/requests/'.$r['id'],['action'=>'manage','status'=>'Pending','expected_version'=>$r['record_version']])->assertForbidden();
  $r=$this->actingAs(User::findOrFail($r['requester_id']))->patchJson('/api/requests/'.$r['id'],['action'=>'reply','message'=>'The affected room is 204.','expected_version'=>$r['record_version']])->assertOk()->json();
  $this->actingAs($this->user('personnel'))->patchJson('/api/requests/'.$r['id'],['action'=>'manage','status'=>'Assigned','expected_version'=>$r['record_version']])->assertForbidden();
  $this->assertSame($r['record_version'],ServiceRequest::findOrFail($r['id'])->record_version);
  $this->actingAs($admin)->patchJson('/api/requests/'.$r['id'],['action'=>'manage','status'=>'Assigned','expected_version'=>$r['record_version']])->assertOk();
 }
 public function test_foreign_office_admin_creation_is_rejected_before_commit():void {
  $admin=$this->user('admin');$admin->office_id=DB::table('offices')->where('office_code','Science')->value('id');$admin->save();
  $count=ServiceRequest::count();
  $this->actingAs($admin)->postJson('/api/requests',['service_type_id'=>DB::table('service_types')->value('id'),'subject'=>'Synthetic request','description'=>'This must not leave a committed inaccessible request.','location'=>'Test room','urgency'=>'Low','impact'=>'Individual'])->assertForbidden();
  $this->assertDatabaseCount('service_requests',$count);
 }
 public function test_feedback_and_login_have_independent_rate_buckets():void {
  config(['security.feedback_per_minute'=>2]);
  $this->postJson('/api/feedback',$this->feedback())->assertCreated();
  $this->postJson('/api/feedback',$this->feedback())->assertCreated();
  $this->postJson('/api/feedback',$this->feedback())->assertTooManyRequests();
  $this->postJson('/api/login',['email'=>'missing@clsu.demo','password'=>'synthetic incorrect password'])->assertUnprocessable();
 }
 public function test_account_login_limit_applies_across_different_ips():void {
  config(['security.login_account_per_minute'=>2]);
  foreach(['192.0.2.1','192.0.2.2'] as $ip)$this->withServerVariables(['REMOTE_ADDR'=>$ip])->postJson('/api/login',['email'=>'missing@clsu.demo','password'=>'synthetic incorrect password'])->assertUnprocessable();
  $this->withServerVariables(['REMOTE_ADDR'=>'192.0.2.3'])->postJson('/api/login',['email'=>'MISSING@clsu.demo','password'=>'synthetic incorrect password'])->assertTooManyRequests();
 }
 public function test_missing_origin_and_spoofed_host_do_not_authorize_writes():void {
  $this->withHeaders(['Origin'=>''])->postJson('/api/feedback',$this->feedback())->assertForbidden();
  $this->withHeaders(['Origin'=>'http://untrusted.example','Host'=>'untrusted.example'])->postJson('/api/feedback',$this->feedback())->assertForbidden();
  $this->withHeaders(['Origin'=>'','Referer'=>config('app.url').'/feedback','Host'=>'127.0.0.1'])->postJson('/api/feedback',$this->feedback())->assertCreated();
 }
 public function test_internal_and_noop_updates_do_not_notify_requesters():void {
  $r=ServiceRequest::where('status','Pending')->firstOrFail();$before=DB::table('portal_notifications')->where('user_id',$r->requester_id)->count();
  $this->actingAs($this->user('admin'))->patchJson('/api/requests/'.$r->id,['action'=>'manage','expected_version'=>1])->assertUnprocessable();
  $this->assertSame(1,$r->fresh()->record_version);
  $this->patchJson('/api/requests/'.$r->id,['action'=>'manage','message'=>'Private diagnostic follow-up','visibility'=>'internal','expected_version'=>1])->assertOk();
  $this->assertNull($r->fresh()->first_response_at);
  $this->assertSame($before,DB::table('portal_notifications')->where('user_id',$r->requester_id)->count());
 }
 public function test_requester_reply_notifies_reviewers_not_the_requester():void {
  $r=ServiceRequest::where('status','For Clarification')->firstOrFail();$admin=$this->user('admin');
  $ownerCount=DB::table('portal_notifications')->where('user_id',$r->requester_id)->count();$staffCount=DB::table('portal_notifications')->where('user_id',$admin->id)->count();
  $this->actingAs(User::findOrFail($r->requester_id))->patchJson('/api/requests/'.$r->id,['action'=>'reply','message'=>'The event is on Friday in room 204.','expected_version'=>1])->assertOk();
  $this->assertSame($ownerCount,DB::table('portal_notifications')->where('user_id',$r->requester_id)->count());
  $this->assertSame($staffCount+1,DB::table('portal_notifications')->where('user_id',$admin->id)->count());
 }
 public function test_explicit_unassignment_works_during_review_but_not_active_work():void {
  $r=$this->assigned();$this->actingAs($this->user('admin'))->patchJson('/api/requests/'.$r->id,['action'=>'manage','personnel_id'=>null,'expected_version'=>1])->assertUnprocessable();
  $this->assertTrue(DB::table('assignments')->where('service_request_id',$r->id)->whereNull('ended_at')->exists());
  $r->status='Pending';$r->save();
  $this->patchJson('/api/requests/'.$r->id,['action'=>'manage','personnel_id'=>null,'expected_version'=>1])->assertOk()->assertJsonPath('assignee_id',null);
  $this->assertFalse(DB::table('assignments')->where('service_request_id',$r->id)->whereNull('ended_at')->exists());
 }
 public function test_active_report_cap_prevents_unbounded_queue_growth():void {
  \Illuminate\Support\Facades\Queue::fake();
  $this->actingAs($this->user('admin'));
  $this->postJson('/api/reports',['days'=>30])->assertAccepted();
  $this->postJson('/api/reports',['days'=>30])->assertAccepted();
  $this->postJson('/api/reports',['days'=>30])->assertTooManyRequests();
  $this->assertDatabaseCount('report_jobs',2);
 }
 public function test_report_rate_limit_is_independent_of_active_job_cap():void {
  config(['security.report_per_minute'=>1]);$this->actingAs($this->user('admin'));
  $this->postJson('/api/reports',['days'=>30])->assertAccepted();
  $this->postJson('/api/reports',['days'=>30])->assertTooManyRequests();
 }
 public function test_missing_completed_report_file_returns_a_safe_not_found():void {
  Storage::fake('local');$admin=$this->user('admin');
  $id=DB::table('report_jobs')->insertGetId(['requested_by'=>$admin->id,'status'=>'completed','filters'=>json_encode(['days'=>30,'handling_office_id'=>$admin->office_id]),'path'=>'reports/missing.csv','created_at'=>now(),'updated_at'=>now()]);
  $this->actingAs($admin)->getJson('/api/reports/'.$id.'/download')->assertNotFound()->assertJsonPath('message','This report file is no longer available. Request a new export.');
 }
 public function test_csv_neutralizes_whitespace_prefixed_formula_content():void {
  Storage::fake('local');$admin=$this->user('admin');$r=$this->assigned();
  // This check needs an eligible submission even when run before the seed's 09:20 timestamp.
  $r->created_at=now()->subHour();$r->save();
  $job=DB::table('report_jobs')->insertGetId(['requested_by'=>$admin->id,'status'=>'pending','filters'=>json_encode(['days'=>30,'handling_office_id'=>$admin->office_id]),'created_at'=>now(),'updated_at'=>now()]);
  foreach(["=1+1","\n=1+1","  @SUM(1)","\t+1","|test","\u{00a0}-1","\u{FEFF}=1+1","\u{200B}+1","\u{202E}@SUM(1)"," \u{FEFF}\u{200B}-1","\u{FE0F}=1+1","Ordinary service text","\u{200B}Ordinary service text"] as $subject){
   $r->subject=$subject;$r->save();DB::table('report_jobs')->where('id',$job)->update(['status'=>'pending']);
   app()->call([new GenerateReport($job),'handle']);
   $stream=fopen('php://temp','r+');fwrite($stream,Storage::disk('local')->get('reports/'.$job.'.csv'));rewind($stream);$found=false;
   while(($row=fgetcsv($stream,0,',','"',''))!==false)if($row[0]===$r->job_number){$safe=in_array($subject,["Ordinary service text","\u{200B}Ordinary service text"],true);$this->assertSame(($safe?'':"'").$subject,$row[1]);$found=true;}
   fclose($stream);$this->assertTrue($found);
  }
 }
 public function test_database_guard_rejects_live_external_or_cached_targets():void {
  foreach([
   ['testing',false,'mysql',['database'=>'clsu_feedback','host'=>'127.0.0.1','port'=>3307]],
   ['testing',false,'mysql',['database'=>'clsu_feedback_test','host'=>'remote.example','port'=>3307]],
   ['testing',false,'sqlite',['database'=>'development.sqlite']],
   ['testing',true,'sqlite',['database'=>':memory:']],
   ['local',false,'sqlite',['database'=>':memory:']],
   ['testing',false,'mysql',['database'=>'clsu_feedback_test','host'=>'127.0.0.1','port'=>3307,'url'=>'mysql://untrusted']],
  ] as $case){
   try {TestEnvironmentGuard::assertSafe(...$case);$this->fail('Unsafe test target was accepted.');}
   catch(\RuntimeException $e){$this->assertStringContainsString('Refusing destructive tests',$e->getMessage());}
  }
 }
}
