<?php
namespace App\Integrations\Mcp;

use App\Models\User;
use Illuminate\Support\Facades\{DB, Validator};
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Local shared-service diagnostics only. No provider HTTP client or outbound transport.
class LocalAssistant
{
    public const TOOL_CLASSES = [Tools\SearchRequests::class, Tools\GetRequestDetails::class, Tools\GetPersonnelWorkload::class, Tools\GetFeedbackMetrics::class, Tools\GetReportJob::class];
    public function read(User $user, string $name, array $arguments): array
    {
        $auth = app(McpAuthorization::class); $auth->assertAdministrator($user);
        abort_unless(app(AssistantSettings::class)->syntheticEnvironment() && str_ends_with($user->email, '@clsu.demo'), 403, 'Local assistant checks are restricted to the synthetic demo environment.');
        $tools = [];
        foreach (self::TOOL_CLASSES as $class) { $tool = app($class); $tools[$tool->name()] = $tool; }
        abort_unless(isset($tools[$name]), 403, 'Only the listed read tools are available.');
        $issued = $auth->issue($user, 'Local synthetic tool check', [$name], 1);
        DB::table('mcp_authorizations')->where('id', $issued['id'])->update(['client_kind' => 'local']);
        $audit = DB::table('mcp_invocations')->insertGetId(['authorization_id' => $issued['id'], 'tool_name' => $name, 'outcome' => 'processing', 'started_at' => now()]);
        $outcome = 'failed'; $code = 'invalid_input'; $refs = [];
        try {
            $grant = $auth->authenticate($issued['token']); abort_unless($auth->permits($grant, $name), 403);
            abort_unless(!array_diff(array_keys($arguments), array_keys($tools[$name]->validationRules())), 422, 'Unknown tool arguments are not allowed.');
            $input = Validator::make($arguments, $tools[$name]->validationRules())->validate();
            $code = 'temporary_failure'; $data = app(McpReadService::class)->execute($name, $grant->user, $input);
            if ($name === 'get_request_details') $refs['service_request_id'] = $input['request_id'];
            if ($name === 'get_report_job') $refs['report_job_id'] = $input['report_job_id'];
            $outcome = 'successful'; $code = null;
            return ['state' => 'successful', 'tool' => $name, 'data' => $data, 'data_mode' => 'local_only'];
        } catch (\Throwable $e) {
            if ($e instanceof HttpExceptionInterface) $code = match ($e->getStatusCode()) {403 => 'permission_denied', 404 => 'not_found', 422 => 'invalid_input', default => 'temporary_failure'};
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) $code = 'not_found';
            throw $e;
        } finally {
            try { app(McpInvocationAudit::class)->finish($audit, ['outcome' => $outcome, 'error_code' => $code, 'finished_at' => now(), ...$refs]); }
            finally { $auth->revoke($issued['id']); }
        }
    }
}
