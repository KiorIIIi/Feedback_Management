<?php
namespace Tests\Feature;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BugAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_feature_tests_use_an_isolated_in_memory_cache(): void
    {
        $this->assertSame('array', config('cache.default'));
        $this->assertInstanceOf(\Illuminate\Cache\ArrayStore::class, app('cache')->store()->getStore());
    }

    protected function setUp(): void
    {
        parent::setUp();
        // These tests exercise authorization and workflow, rather than password cost.
        config(['hashing.bcrypt.rounds' => 4]);
        $this->seed();
    }

    private function user(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    public function test_internal_status_notes_do_not_leak_into_the_requester_timeline(): void
    {
        $personnel = $this->user('personnel');
        $request = ServiceRequest::where('status', 'In Progress')->whereIn('id',
            DB::table('assignments')->where('personnel_id', $personnel->id)->select('service_request_id')
        )->firstOrFail();
        $privateNote = 'Private diagnostic account details for staff only.';

        $this->actingAs($personnel)->patchJson('/api/requests/'.$request->id, [
            'action' => 'manage', 'expected_version' => $request->record_version,
            'status' => 'On Hold', 'message' => $privateNote, 'visibility' => 'internal',
        ])->assertOk()->assertSee($privateNote);

        $this->actingAs(User::findOrFail($request->requester_id))
            ->getJson('/api/requests/'.$request->id)->assertOk()
            ->assertJsonPath('status', 'On Hold')->assertDontSee($privateNote);
        $this->assertDatabaseHas('request_events', [
            'service_request_id' => $request->id, 'message' => $privateNote, 'visibility' => 'internal',
        ]);
    }

    public function test_disabling_an_account_revokes_its_existing_session(): void
    {
        $user = $this->user('requester');
        $this->actingAs($user)->getJson('/api/me')->assertOk();
        $user->is_active = false;
        $user->save();

        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/requests')->assertUnauthorized();
    }

    public function test_personnel_lose_old_office_record_access_after_transfer(): void
    {
        $personnel = $this->user('personnel');
        $request = ServiceRequest::whereIn('id', DB::table('assignments')
            ->where('personnel_id', $personnel->id)->whereNull('ended_at')->select('service_request_id')
        )->firstOrFail();
        $this->actingAs($personnel)->getJson('/api/requests/'.$request->id)->assertOk();
        $personnel->office_id = DB::table('offices')->where('office_code', 'Science')->value('id');
        $personnel->save();

        $this->getJson('/api/requests/'.$request->id)->assertNotFound();
        $this->getJson('/api/requests')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson('/api/requests/'.$request->id, [
            'action' => 'manage', 'expected_version' => $request->record_version,
            'message' => 'This update must not be accepted after transfer.',
        ])->assertNotFound();
    }

    public function test_former_assignees_do_not_regain_access_when_a_reassigned_job_completes(): void
    {
        $former = $this->user('personnel');
        $replacement = User::where('role', 'personnel')->where('id', '!=', $former->id)->firstOrFail();
        $request = ServiceRequest::where('status', 'In Progress')->whereIn('id',
            DB::table('assignments')->where('personnel_id', $former->id)->select('service_request_id')
        )->firstOrFail();
        $updated = $this->actingAs($this->user('admin'))->patchJson('/api/requests/'.$request->id, [
            'action' => 'manage', 'expected_version' => $request->record_version,
            'personnel_id' => $replacement->id,
        ])->assertOk()->json();
        $this->actingAs($former)->getJson('/api/requests/'.$request->id)->assertNotFound();

        $this->actingAs($replacement)->patchJson('/api/requests/'.$request->id, [
            'action' => 'manage', 'expected_version' => $updated['record_version'],
            'status' => 'Completed', 'message' => 'Restored service.', 'outcome' => 'Validated the repair.',
        ])->assertOk();
        $this->getJson('/api/requests/'.$request->id)->assertOk();
        $this->actingAs($former)->getJson('/api/requests/'.$request->id)->assertNotFound();
    }

    public function test_priority_review_uses_only_the_current_effective_matrix(): void
    {
        $request = ServiceRequest::where('status', 'Pending')->firstOrFail();
        $currentRule = DB::table('priority_rules')->where('urgency', $request->urgency)
            ->where('impact', $request->impact)->first();
        foreach ([['future', now()->addMonth()->toDateString(), null],
            ['expired', now()->subYears(2)->toDateString(), now()->subYear()->toDateString()]] as [$label, $from, $until]) {
            $version = DB::table('priority_rule_versions')->insertGetId([
                'version_number' => $label, 'effective_from' => $from, 'effective_until' => $until,
            ]);
            DB::table('priority_rules')->insert([
                'priority_rule_version_id' => $version, 'urgency' => $request->urgency,
                'impact' => $request->impact, 'resulting_priority' => 'Critical',
            ]);
        }

        $this->actingAs($this->user('admin'))->patchJson('/api/requests/'.$request->id, [
            'action' => 'manage', 'expected_version' => $request->record_version,
            'priority' => $currentRule->resulting_priority,
        ])->assertOk()->assertJsonPath('priority_rule_id', $currentRule->id);
    }

    public function test_same_host_with_an_untrusted_origin_port_cannot_write(): void
    {
        config(['app.url' => 'http://127.0.0.1:5173']);
        $this->withHeaders(['Origin' => 'http://127.0.0.1:9999', 'Sec-Fetch-Site' => 'same-site'])
            ->postJson('/api/feedback', ['category' => 'Other', 'subject' => 'Origin check',
                'message' => 'A different port must not submit this feedback.'])
            ->assertForbidden();
    }

    public function test_configured_frontend_origin_can_write_through_the_api_proxy(): void
    {
        config(['app.url' => 'http://127.0.0.1:5173']);
        $this->withHeaders(['Origin' => 'http://127.0.0.1:5173', 'Sec-Fetch-Site' => 'same-origin'])
            ->postJson('/api/feedback', ['category' => 'Other', 'subject' => 'Trusted origin check',
                'message' => 'The configured frontend can submit through its API proxy.'])
            ->assertCreated();
    }

    public function test_same_host_with_a_different_origin_scheme_cannot_write(): void
    {
        config(['app.url' => 'https://portal.example']);
        $this->withHeaders(['Origin' => 'http://portal.example', 'Sec-Fetch-Site' => 'same-site'])
            ->postJson('/api/feedback', ['category' => 'Other', 'subject' => 'Scheme check',
                'message' => 'An insecure origin cannot impersonate the HTTPS frontend.'])
            ->assertForbidden();
    }

    public function test_api_auth_errors_are_json_without_an_accept_header(): void
    {
        $this->get('/api/requests')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_reading_a_notification_again_preserves_its_first_read_time(): void
    {
        $user = $this->user('requester');
        $notice = DB::table('portal_notifications')->where('user_id', $user->id)->first();
        $this->travelTo(now()->startOfHour());
        $this->actingAs($user)->postJson('/api/notifications/'.$notice->id.'/read')->assertOk();
        $firstRead = DB::table('portal_notifications')->where('id', $notice->id)->value('read_at');
        $this->postJson('/api/notifications/'.$notice->id.'/read')->assertOk();
        $this->travel(1)->hours();
        $this->postJson('/api/notifications/'.$notice->id.'/read')->assertOk();
        $this->assertSame($firstRead, DB::table('portal_notifications')->where('id', $notice->id)->value('read_at'));
    }
}
