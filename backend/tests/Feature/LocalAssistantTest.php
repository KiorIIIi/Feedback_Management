<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

class LocalAssistantTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(); Http::preventStrayRequests(); $this->actingAs(User::where('email','admin@clsu.demo')->firstOrFail()); }
    public function test_provider_endpoints_are_disabled_even_with_saved_settings(): void
    {
        DB::table('assistant_settings')->where('id',1)->update(['assistant_enabled'=>true]);
        $this->getJson('/api/assistant/settings')->assertOk()->assertJsonPath('provider_calls_enabled',false)->assertJsonPath('assistant_enabled',false);
        $this->postJson('/api/assistant/chat',['messages'=>[['role'=>'user','content'=>'Synthetic example']]])->assertConflict();
        $this->postJson('/api/assistant/test',[])->assertConflict(); Http::assertNothingSent();
    }
    public function test_local_read_is_scoped_audited_and_has_no_provider_calls(): void
    {
        $before=DB::table('service_requests')->pluck('record_version','id')->all();
        $this->postJson('/api/assistant/local-read',['tool'=>'search_requests','arguments'=>['status'=>'Pending']])->assertOk()->assertJsonPath('data_mode','local_only')->assertJsonPath('state','successful');
        $this->assertSame($before,DB::table('service_requests')->pluck('record_version','id')->all());
        $this->assertDatabaseHas('mcp_invocations',['tool_name'=>'search_requests','outcome'=>'successful']);
        $this->assertNotNull(DB::table('mcp_authorizations')->where('client_kind','local')->value('revoked_at'));
        $this->getJson('/api/assistant/settings')->assertJsonPath('last_external_call_at',null); Http::assertNothingSent();
    }
    public function test_local_tools_cannot_write_override_identity_or_leave_demo_scope(): void
    {
        $this->postJson('/api/assistant/local-read',['tool'=>'complete_request'])->assertUnprocessable();
        $this->postJson('/api/assistant/local-read',['tool'=>'search_requests','arguments'=>['user_id'=>999]])->assertUnprocessable();
        $this->postJson('/api/assistant/local-read',['tool'=>'get_request_details','arguments'=>['request_id'=>999999]])->assertNotFound();
        config(['security.demo_seed'=>false]);
        $this->postJson('/api/assistant/local-read',['tool'=>'search_requests'])->assertForbidden(); Http::assertNothingSent();
    }
}
