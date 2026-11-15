<?php
namespace Tests\Feature;

use App\Jobs\GenerateReport;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\ProductionConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackendAuditRemediationTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->seed(); }

    private function user(string $role): User { return User::where('role', $role)->firstOrFail(); }

    private function payload(): array {
        return ['service_type_id'=>DB::table('service_types')->first()->id,'requesting_office_id'=>$this->user('requester')->office_id,
            'subject'=>'Regression check for audit remediation','description'=>'The laboratory Wi-Fi connection is unstable and needs inspection.',
            'location'=>'Engineering laboratory','urgency'=>'Medium','impact'=>'Entire office'];
    }

    public function test_manage_with_message_records_one_requester_event(): void {
        $owner = $this->user('requester'); $admin = $this->user('admin'); $personnel = $this->user('personnel');
        $r = $this->actingAs($owner)->postJson('/api/requests', $this->payload())->assertCreated()->json();
        $this->actingAs($admin)->patchJson('/api/requests/'.$r['id'], [
            'action'=>'manage','expected_version'=>1,'personnel_id'=>$personnel->id,
            'status'=>'Assigned','message'=>'Assigned to you for inspection.','visibility'=>'requester',
        ])->assertOk();

        $events = DB::table('request_events')->where('service_request_id', $r['id'])
            ->where('visibility', 'requester')->get();
        $this->assertCount(1, $events->where('message', 'Assigned to you for inspection.'));
        $this->assertCount(0, $events->where('kind', 'Progress update'));
    }

    public function test_metrics_excludes_out_of_range_ratings(): void {
        $admin = $this->user('admin');
        $request = ServiceRequest::where('handling_office_id', $admin->office_id)->where('status', 'Completed')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('service_evaluations')->whereColumn('request_id', 'service_requests.id'))
            ->firstOrFail();
        $before = $this->actingAs($admin)->getJson('/api/metrics?days=366')->assertOk()->json();
        DB::table('service_evaluations')->insert(['request_id'=>$request->id, 'rating'=>9, 'created_at'=>now()]);
        $after = $this->actingAs($admin)->getJson('/api/metrics?days=366')->assertOk()->json();
        $this->assertSame($before['rating_count'], $after['rating_count']);
        $this->assertSame($before['satisfaction'], $after['satisfaction']);
    }

    public function test_report_summary_has_no_request_rows(): void {
        $admin = $this->user('admin');
        $response = $this->actingAs($admin)->getJson('/api/report-summary?days=30')->assertOk();
        $response->assertJsonMissingPath('requests');
        $summary = $response->json('summary');
        $this->assertArrayHasKey('total', $summary);
        $this->assertArrayHasKey('completion_rate', $summary);
    }

    public function test_request_list_keeps_presented_fields(): void {
        $requester = $this->user('requester');
        $response = $this->actingAs($requester)->getJson('/api/requests')->assertOk()->json('data');
        $this->assertNotEmpty($response);
        foreach (['job_number','office','service_type','category','assignee','evaluation'] as $key) {
            $this->assertArrayHasKey($key, $response[0]);
        }
    }

    public function test_category_creation_requires_a_handling_office(): void {
        $admin = $this->user('admin');
        $admin->office_id = null; $admin->save();
        $this->actingAs($admin->fresh())->postJson('/api/request-categories', ['name'=>'Audit category'])
            ->assertForbidden();
    }

    public function test_cors_exposes_the_registered_admin_methods(): void {
        $methods = config('cors.allowed_methods');
        $this->assertContains('PUT', $methods);
        $this->assertContains('DELETE', $methods);
    }

    public function test_report_queue_window_exceeds_the_job_timeout(): void {
        $job = new GenerateReport(1);
        $this->assertGreaterThan($job->timeout, config('queue.connections.database.retry_after'));
        $this->assertNotEmpty($job->backoff);
    }

    public function test_production_guard_rejects_the_sync_queue(): void {
        $settings = [
            'app'=>['env'=>'production','debug'=>false,'url'=>'https://portal.example.edu','key'=>'base64:'.base64_encode(str_repeat('a', 32))],
            'session'=>['secure'=>true,'encrypt'=>true],
            'database'=>['default'=>'mysql','connections'=>['mysql'=>['database'=>'clsu_feedback_prod','username'=>'portal','password'=>'long-random-password']]],
            'security'=>['trusted_origins'=>[],'trusted_proxies'=>[]], 'demo_seed'=>false,
            'mcp'=>['enabled'=>false], 'queue'=>['default'=>'sync'],
        ];
        try { ProductionConfiguration::assertSafe($settings); $this->fail('Sync queue should fail the production guard.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('QUEUE_CONNECTION', $e->getMessage()); }
        $settings['queue']['default'] = 'database';
        ProductionConfiguration::assertSafe($settings);
        $this->assertTrue(true);
    }
}
