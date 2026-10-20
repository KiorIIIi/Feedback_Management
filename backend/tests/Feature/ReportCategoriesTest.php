<?php
namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportCategoriesTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        config(['hashing.bcrypt.rounds'=>4]);
        $this->seed();
    }

    public function test_category_foreign_key_preserves_office_scope(): void {
        $request=DB::table('service_requests')->first();
        $foreign=DB::table('offices')->where('id','!=',$request->handling_office_id)->first();
        $category=DB::table('request_categories')->insertGetId(['handling_office_id'=>$foreign->id,'name'=>'Synthetic foreign category']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('service_requests')->where('id',$request->id)->update(['category_id'=>$category]);
    }

    public function test_only_admin_can_create_office_categories_and_duplicates_are_rejected(): void {
        $admin=User::where('role','admin')->firstOrFail();
        foreach (['requester','personnel'] as $role) {
            $this->actingAs(User::where('role',$role)->firstOrFail())->postJson('/api/request-categories',['name'=>'Synthetic category'])->assertForbidden();
        }
        $category=$this->actingAs($admin)->postJson('/api/request-categories',['name'=>'  Synthetic   category  '])->assertCreated()->json();
        $this->assertSame($admin->office_id,$category['handling_office_id']);
        $this->assertSame('Synthetic category',$category['name']);
        $this->postJson('/api/request-categories',['name'=>'synthetic category'])->assertUnprocessable();
        $this->postJson('/api/request-categories',['name'=>'  '])->assertUnprocessable();
        $this->assertDatabaseCount('request_categories',1);
        $this->assertDatabaseHas('audit_entries',['action'=>'request_category.created','actor_user_id'=>$admin->id]);
        $foreign=DB::table('offices')->where('id','!=',$admin->office_id)->first();
        DB::table('request_categories')->insert(['handling_office_id'=>$foreign->id,'name'=>'Other office private taxonomy']);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(1,'categories')->assertDontSee('Other office private taxonomy');
    }

    public function test_category_review_checks_version_scope_and_transactional_history(): void {
        $admin=User::where('role','admin')->firstOrFail();
        $request=ServiceRequest::where('status','Pending')->firstOrFail();
        $category=$this->actingAs($admin)->postJson('/api/request-categories',['name'=>'Synthetic review category'])->assertCreated()->json('id');
        $payload=['action'=>'manage','expected_version'=>$request->record_version,'category_id'=>$category];
        $events=DB::table('request_events')->count(); $audits=DB::table('audit_entries')->count();
        $this->patchJson('/api/requests/'.$request->id,[...$payload,'status'=>'Completed'])->assertUnprocessable();
        $this->assertNull($request->fresh()->category_id);
        $this->assertDatabaseCount('request_events',$events);$this->assertDatabaseCount('audit_entries',$audits);
        $this->patchJson('/api/requests/'.$request->id,$payload)->assertOk()->assertJsonPath('category','Synthetic review category')->assertJsonPath('record_version',2);
        $this->assertDatabaseHas('request_events',['service_request_id'=>$request->id,'kind'=>'Category reviewed','visibility'=>'internal']);
        $this->assertDatabaseCount('audit_entries',$audits+1);
        $this->patchJson('/api/requests/'.$request->id,[...$payload,'category_id'=>null])->assertConflict();
        $foreign=DB::table('offices')->where('id','!=',$admin->office_id)->first();
        $foreignId=DB::table('request_categories')->insertGetId(['handling_office_id'=>$foreign->id,'name'=>'Foreign category']);
        $this->patchJson('/api/requests/'.$request->id,[...$payload,'expected_version'=>2,'category_id'=>$foreignId])->assertUnprocessable();
        $this->actingAs(User::findOrFail($request->requester_id))->getJson('/api/requests/'.$request->id)->assertOk()->assertDontSee('Category reviewed');
        $this->patchJson('/api/requests/'.$request->id,[...$payload,'expected_version'=>2])->assertForbidden();
        $assigned=ServiceRequest::where('status','In Progress')->firstOrFail();
        $personnel=User::findOrFail(DB::table('assignments')->where('service_request_id',$assigned->id)->whereNull('ended_at')->value('personnel_id'));
        $this->actingAs($personnel)->patchJson('/api/requests/'.$assigned->id,[...$payload,'expected_version'=>$assigned->record_version])->assertForbidden();
        $this->actingAs($admin)->patchJson('/api/requests/'.$request->id,[...$payload,'expected_version'=>2,'category_id'=>null])->assertOk()->assertJsonPath('category','Uncategorized');
        $completed=ServiceRequest::where('status','Completed')->firstOrFail();
        $this->patchJson('/api/requests/'.$completed->id,[...$payload,'expected_version'=>$completed->record_version])->assertUnprocessable();
    }
}
