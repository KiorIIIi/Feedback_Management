<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateMcp;
use App\Integrations\Mcp\McpAuthorization;
use App\Integrations\Mcp\McpInvocationAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DeepseekSecurityFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_environment_flag_parsing_never_enables_off_values_or_unknown_strings(): void
    {
        $script = 'require "vendor/autoload.php"; try { $config = require "config/mcp.php"; echo json_encode($config["enabled"]); } catch (RuntimeException $e) { fwrite(STDERR, $e->getMessage()); exit(1); }';
        foreach (['true' => true, '1' => true, 'on' => true, 'yes' => true, 'false' => false, '0' => false, 'off' => false, 'no' => false, '' => false, 'disabled' => null, 'unexpected-secret-value' => null] as $value => $expected) {
            $process = new Process([PHP_BINARY, '-r', $script], base_path(), ['MCP_ENABLED' => (string) $value]);
            $process->run();
            if ($expected === null) {
                $this->assertSame(1, $process->getExitCode());
                $this->assertStringContainsString('Invalid MCP_ENABLED', $process->getErrorOutput());
                $this->assertStringNotContainsString((string) $value, $process->getErrorOutput());
            } else {
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $this->assertSame($expected, json_decode($process->getOutput(), true));
            }
        }
        $process = new Process([PHP_BINARY, '-r', $script], base_path(), ['MCP_ENABLED' => false]);
        $process->mustRun();
        $this->assertSame('false', $process->getOutput());
        foreach (['off', 'true', 1, false] as $value) {
            config(['mcp.enabled' => $value]);
            $this->postJson('/api/mcp', ['method' => 'tools/list'])->assertNotFound();
        }
    }

    public function test_reconciliation_preserves_recent_and_terminal_rows_and_is_repeatable(): void
    {
        $this->seed();
        $grant = app(McpAuthorization::class)->issue(User::where('role', 'admin')->firstOrFail(), 'Synthetic audit recovery', ['search_requests']);
        $base = ['authorization_id' => $grant['id'], 'tool_name' => 'search_requests'];
        $stale = DB::table('mcp_invocations')->insertGetId([...$base, 'outcome' => 'processing', 'started_at' => now()->subHours(2)]);
        $recent = DB::table('mcp_invocations')->insertGetId([...$base, 'outcome' => 'processing', 'started_at' => now()->subMinutes(5)]);
        foreach (['successful', 'failed'] as $outcome) {
            DB::table('mcp_invocations')->insert([...$base, 'outcome' => $outcome, 'started_at' => now()->subHours(2), 'finished_at' => now()->subHour()]);
        }
        $terminal = DB::table('mcp_invocations')->whereNotNull('finished_at')->get()->toArray();
        foreach (['0', '-1', 'abc', '10081'] as $minutes) {
            $this->artisan('mcp:reconcile-invocations', ['--minutes' => $minutes])->assertExitCode(1);
        }
        $this->assertDatabaseHas('mcp_invocations', ['id' => $stale, 'outcome' => 'processing']);
        $this->artisan('mcp:reconcile-invocations')->expectsOutput('Reconciled 1 stale MCP invocations; execution outcomes remain unknown.')->assertExitCode(0);
        $this->assertDatabaseHas('mcp_invocations', ['id' => $stale, 'outcome' => 'failed', 'error_code' => 'interrupted_unknown']);
        $this->assertNotNull(DB::table('mcp_invocations')->find($stale)->finished_at);
        $this->assertDatabaseHas('mcp_invocations', ['id' => $recent, 'outcome' => 'processing', 'finished_at' => null]);
        $this->assertEquals($terminal, DB::table('mcp_invocations')->whereIn('outcome', ['successful', 'failed'])->where('id', '!=', $stale)->get()->toArray());
        $this->artisan('mcp:reconcile-invocations')->expectsOutput('Reconciled 0 stale MCP invocations; execution outcomes remain unknown.')->assertExitCode(0);
        $this->expectException(\RuntimeException::class);
        app(McpInvocationAudit::class)->finish($stale, ['outcome' => 'successful', 'finished_at' => now()]);
    }

    public function test_content_is_not_returned_if_reconciliation_wins_during_a_call(): void
    {
        $this->seed();
        config(['mcp.enabled' => true]);
        $grant = app(McpAuthorization::class)->issue(User::where('role', 'admin')->firstOrFail(), 'Synthetic interrupted read', ['search_requests']);
        $request = Request::create('/api/mcp', 'POST', ['method' => 'tools/call', 'params' => ['name' => 'search_requests']], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$grant['token']]);
        try {
            app(AuthenticateMcp::class)->handle($request, function () {
                DB::table('mcp_invocations')->update(['started_at' => now()->subHours(2)]);
                app(McpInvocationAudit::class)->reconcile(now()->subHour());
                return response()->json(['result' => ['content' => 'Synthetic private record']]);
            });
            $this->fail('A reconciled invocation must not release record content.');
        } catch (\RuntimeException $error) {
            $this->assertSame('MCP invocation could not be finalized.', $error->getMessage());
        }
        $this->assertDatabaseHas('mcp_invocations', ['outcome' => 'failed', 'error_code' => 'interrupted_unknown', 'service_request_id' => null]);
    }
}
