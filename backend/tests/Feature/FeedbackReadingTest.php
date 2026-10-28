<?php
namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeedbackReadingTest extends TestCase {
    use RefreshDatabase;
    protected function setUp(): void {parent::setUp();config(['hashing.bcrypt.rounds'=>4]);$this->seed();}
    private function user(string $role): User {return User::where('role',$role)->firstOrFail();}
    private function feedback(?int $owner, array $values=[]): int {
        return DB::table('general_feedback')->insertGetId([
            'user_id'=>$owner,'category'=>'Suggestion','subject'=>'Synthetic full feedback',
            'message'=>str_repeat("Synthetic detailed feedback line.\n",200).'End of full message.',
            'contact_name'=>'Synthetic Tester','contact_email'=>'synthetic@example.invalid',
            'unverified_job_number'=>'UNVERIFIED-123','created_at'=>now(),
            ...$values,
        ]);
    }
    public function test_full_general_feedback_is_private_to_miso_and_its_authenticated_owner(): void {
        $owner=$this->user('requester');$id=$this->feedback($owner->id);
        $path='/api/feedback/general/'.$id;
        $this->getJson($path)->assertUnauthorized();
        $this->actingAs($this->user('admin'))->getJson($path)->assertOk()->assertJsonPath('kind','general')
            ->assertJsonPath('record.contact_email','synthetic@example.invalid')->assertSee('End of full message.');
        $this->actingAs($owner)->getJson($path)->assertOk();
        $other=User::where('role','requester')->where('id','!=',$owner->id)->firstOrFail();
        $this->actingAs($other)->getJson($path)->assertNotFound();
        $this->actingAs($this->user('personnel'))->getJson($path)->assertForbidden();
        $admin=$this->user('admin');$admin->office_id=DB::table('offices')->where('office_code','Science')->value('id');$admin->save();
        $this->actingAs($admin)->getJson($path)->assertNotFound();
        $this->getJson('/api/feedback')->assertJsonCount(0,'general');
    }
    public function test_guest_feedback_remains_miso_only_and_unverified_references_grant_no_access(): void {
        $id=$this->feedback(null);$path='/api/feedback/general/'.$id;
        $this->actingAs($this->user('requester'))->getJson($path)->assertNotFound();
        $this->actingAs($this->user('admin'))->getJson($path)->assertOk()->assertJsonPath('record.unverified_job_number','UNVERIFIED-123');
        $this->getJson('/api/feedback/general/999999')->assertNotFound();
        $this->getJson('/api/feedback/unknown/'.$id)->assertNotFound();
    }
    public function test_verified_evaluation_detail_uses_request_ownership_and_handling_office(): void {
        $request=ServiceRequest::where('status','Completed')->firstOrFail();
        DB::table('service_evaluations')->insert(['request_id'=>$request->id,'rating'=>4,'remarks'=>str_repeat('Synthetic evaluation. ',100).'Final evaluation line.','created_at'=>now()]);
        $path='/api/feedback/evaluation/'.$request->id;
        $this->actingAs($this->user('admin'))->getJson($path)->assertOk()->assertJsonPath('kind','evaluation')->assertJsonPath('record.job_number',$request->job_number)->assertSee('Final evaluation line.');
        $this->actingAs(User::findOrFail($request->requester_id))->getJson($path)->assertOk();
        $other=User::where('role','requester')->where('id','!=',$request->requester_id)->firstOrFail();
        $this->actingAs($other)->getJson($path)->assertNotFound();
        $this->actingAs($this->user('personnel'))->getJson($path)->assertForbidden();
        $admin=$this->user('admin');$admin->office_id=DB::table('offices')->where('office_code','Science')->value('id');$admin->save();
        $this->actingAs($admin)->getJson($path)->assertNotFound();
    }
    public function test_pagination_reaches_feedback_older_than_the_previous_hundred_record_limit(): void {
        $owner=$this->user('requester');$oldest=$this->feedback($owner->id);
        for ($i=0;$i<100;$i++) $this->feedback($owner->id);
        $this->actingAs($this->user('admin'))->getJson('/api/feedback')->assertOk()->assertJsonCount(25,'general')
            ->assertJsonPath('pagination.general.total',101)->assertJsonPath('pagination.general.last_page',5);
        $this->getJson('/api/feedback?general_page=5')->assertOk()->assertJsonCount(1,'general')->assertJsonPath('general.0.id',$oldest);
        $this->getJson('/api/feedback?general_page=0')->assertUnprocessable();
        $this->getJson('/api/feedback?evaluation_page=-1')->assertUnprocessable();
    }
    public function test_filters_run_before_pagination_with_literal_search_and_manila_day_boundaries(): void {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-21 12:00:00','Asia/Manila'));
        $owner=$this->user('requester');
        $match=$this->feedback($owner->id,['subject'=>'Synthetic 100%_network','message'=>'Library connection problem.',
            'category'=>'Complaint','rating'=>2,'created_at'=>'2026-09-22 00:00:00']);
        $this->feedback($owner->id,['subject'=>'Synthetic 100xxnetwork','category'=>'Complaint','rating'=>2]);
        $this->feedback($owner->id,['subject'=>'Synthetic 100%_network','category'=>'Complaint','rating'=>2,'created_at'=>'2026-09-21 23:59:59']);
        $this->feedback($owner->id,['subject'=>'Synthetic 100%_network','category'=>'Complaint','rating'=>2,'created_at'=>'2026-10-22 00:00:00']);
        $this->actingAs($this->user('admin'))->getJson('/api/feedback?'.http_build_query([
            'search'=>'100%_network','rating'=>'low','category'=>'Complaint','days'=>'30',
        ]))->assertOk()->assertJsonCount(1,'general')->assertJsonPath('general.0.id',$match)
            ->assertJsonPath('pagination.general.total',1)->assertJsonPath('counts.general',4);
        $this->getJson('/api/feedback?search=Library')->assertJsonPath('pagination.general.total',1);
        $this->getJson('/api/feedback?search=UNVERIFIED-123')->assertJsonPath('pagination.general.total',4);
        $this->getJson('/api/feedback?rating=unrated')->assertJsonCount(0,'general');
        $this->getJson('/api/feedback?rating=2')->assertJsonCount(4,'general');
    }
    public function test_filter_counts_never_escape_owner_or_miso_scope_and_invalid_filters_fail(): void {
        $owner=$this->user('requester');
        $other=User::where('role','requester')->where('id','!=',$owner->id)->firstOrFail();
        $id=$this->feedback($owner->id);
        $this->feedback($other->id,['rating'=>1]);$this->feedback(null,['rating'=>2]);
        $this->actingAs($owner)->getJson('/api/feedback?rating=unrated')->assertOk()
            ->assertJsonPath('counts.general',1)->assertJsonPath('general.0.id',$id);
        $this->getJson('/api/feedback?rating=low')->assertJsonPath('pagination.general.total',0)->assertJsonPath('counts.general',1);
        $admin=$this->user('admin');$admin->office_id=DB::table('offices')->where('office_code','Science')->value('id');$admin->save();
        $this->actingAs($admin)->getJson('/api/feedback?search=Synthetic')->assertJsonPath('counts.general',0)->assertJsonCount(0,'general');
        foreach (['rating=6','days=31','category=Network','search='.str_repeat('a',201)] as $invalid) {
            $this->getJson('/api/feedback?'.$invalid)->assertUnprocessable();
        }
    }
    public function test_evaluation_filters_and_context_use_the_final_assignment_without_duplicate_rows(): void {
        $request=ServiceRequest::where('status','Completed')->firstOrFail();
        $person=$this->user('personnel');
        $final=User::forceCreate(['name'=>'Synthetic Final Technician','email'=>'final@example.invalid','password'=>'unused',
            'role'=>'personnel','is_active'=>true,'office_id'=>$request->handling_office_id]);
        foreach ([$person,$final] as $assignee) DB::table('assignments')->insert([
            'service_request_id'=>$request->id,'personnel_id'=>$assignee->id,'assigned_by'=>$this->user('admin')->id,
            'assigned_at'=>now()->subDays(2),'ended_at'=>now()->subDay(),
        ]);
        $category=DB::table('request_categories')->insertGetId(['handling_office_id'=>$request->handling_office_id,'name'=>'Synthetic equipment']);
        DB::table('service_requests')->where('id',$request->id)->update(['category_id'=>$category]);
        DB::table('service_evaluations')->insert(['request_id'=>$request->id,'rating'=>2,'remarks'=>'Synthetic library connection review.','created_at'=>now()]);
        $this->actingAs($this->user('admin'))->getJson('/api/feedback?search=library&rating=low&days=30&category=Complaint')
            ->assertOk()->assertJsonCount(1,'evaluations')->assertJsonPath('counts.evaluations',1);
        $this->getJson('/api/feedback?search='.$request->job_number)->assertJsonPath('pagination.evaluations.total',1);
        $this->getJson('/api/feedback/evaluation/'.$request->id)->assertOk()
            ->assertJsonPath('record.final_assigned_personnel','Synthetic Final Technician')
            ->assertJsonPath('record.request_category','Synthetic equipment')
            ->assertJsonPath('record.service_type',DB::table('service_types')->where('id',$request->service_type_id)->value('name'));
        DB::table('service_requests')->where('id',$request->id)->update(['category_id'=>null]);
        $this->getJson('/api/feedback/evaluation/'.$request->id)->assertJsonPath('record.request_category',null);
    }
}
