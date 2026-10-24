<?php

namespace App\Http\Middleware;

use App\Integrations\Mcp\McpAuthorization;
use App\Integrations\Mcp\McpInvocationAudit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AuthenticateMcp
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('mcp.enabled') === true, 404);
        abort_unless(app()->environment(['local', 'testing']) || $request->isSecure(), 403, 'HTTPS is required for MCP.');
        // Stateless bearer transport. Reject untrusted browser origins even with a token.
        if ($request->hasHeader('Origin')) {
            abort_unless(in_array($request->header('Origin'), [rtrim(config('app.url'), '/'), ...config('security.trusted_origins', [])], true), 403);
        }
        $auth = app(McpAuthorization::class);
        $grant = $auth->authenticate($request->bearerToken());
        $request->setUserResolver(fn () => $grant->user);
        $request->attributes->set('mcp_authorization', $grant);
        $invocation = null;
        if ($request->input('method') === 'tools/call') {
            $name = $request->input('params.name');
            // Never persist arbitrary tool strings (which may contain injected data or secrets).
            $tool = is_string($name) && in_array($name, McpAuthorization::TOOLS, true) ? $name : 'unknown';
            $invocation = DB::table('mcp_invocations')->insertGetId([
                'authorization_id' => $grant->id, 'tool_name' => $tool, 'outcome' => 'processing', 'started_at' => now(),
            ]);
        }
        $outcome = 'failed';
        $error = 'temporary_failure';
        try {
            if ($invocation !== null) {
                abort_unless($auth->permits($grant, $tool), 403, 'This tool is not authorized.');
            }
            $response = $next($request);
            $body = json_decode($response->getContent(), true);
            $failed = $response->getStatusCode() >= 400 || isset($body['error']) || ($body['result']['isError'] ?? false);
            $outcome = $failed ? 'failed' : 'successful';
            $error = $failed ? ($request->attributes->get('mcp_error_code') ?? 'protocol_error') : null;
            $response->headers->set('Cache-Control', 'private, no-store');
            return $response;
        } catch (HttpExceptionInterface $exception) {
            $error = match ($exception->getStatusCode()) {
                403 => 'permission_denied', 404 => 'not_found', 429 => 'rate_limited', default => 'invalid_request',
            };
            throw $exception;
        } finally {
            if ($invocation !== null) {
                app(McpInvocationAudit::class)->finish($invocation, [
                    'outcome' => $outcome, 'error_code' => $error, 'finished_at' => now(),
                    'service_request_id' => $request->attributes->get('mcp_request_id'),
                    'report_job_id' => $request->attributes->get('mcp_report_job_id'),
                ]);
            }
        }
    }
}
