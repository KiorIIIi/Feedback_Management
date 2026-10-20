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
    private function feedback(?int $owner): int {
        return DB::table('general_feedback')->insertGetId([
            'user_id'=>$owner,'category'=>'Suggestion','subject'=>'Synthetic full feedback',
            'message'=>str_repeat("Synthetic detailed feedback line.\n",200).'End of full message.',
            'contact_name'=>'Synthetic Tester','contact_email'=>'synthetic@example.invalid',
            'unverified_job_number'=>'UNVERIFIED-123','created_at'=>now(),
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
}
