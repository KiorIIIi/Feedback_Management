<?php

use App\Integrations\Mcp\McpAuthorization;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('mcp:reconcile-invocations {--minutes=60 : Minimum age in minutes (15–10080)}', function () {
    $minutes = filter_var($this->option('minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 15, 'max_range' => 10080]]);
    if ($minutes === false) {
        $this->error('Minimum age must be an integer from 15 to 10080 minutes.');
        return 1;
    }
    $count = app(\App\Integrations\Mcp\McpInvocationAudit::class)->reconcile(now()->subMinutes($minutes));
    $this->info('Reconciled '.$count.' stale MCP invocations; execution outcomes remain unknown.');
    return 0;
})->purpose('Close stale MCP audit entries without asserting an execution outcome');

Schedule::command('mcp:reconcile-invocations')->hourly()->withoutOverlapping();

Artisan::command('mcp:issue {user : User ID} {--client= : Trusted client label} {--tool=* : Explicit read-tool scope (repeatable)} {--hours=24 : Lifetime, 1–168 hours}', function () {
    try {
        $user = User::findOrFail($this->argument('user'));
        $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT);
        if ($hours === false) throw new \InvalidArgumentException('Lifetime must be an integer.');
        $issued = app(McpAuthorization::class)->issue($user, (string) $this->option('client'), $this->option('tool'), $hours);
        $this->info('Authorization ID: '.$issued['id'].'; expires: '.$issued['expires_at']);
        $this->warn('Copy this credential to the trusted client secret store. It is displayed only once; keep terminal output private.');
        $this->line($issued['token']);
        return 0;
    } catch (\Throwable $exception) {
        $this->error('Credential not issued. Verify the active MISO administrator ID, client, read-tool scopes and lifetime.');
        return 1;
    }
})->purpose('Issue an expiring, scoped MCP pilot credential from a trusted operator console');

Artisan::command('mcp:revoke {authorization : Authorization ID}', function () {
    try {
        $id = filter_var($this->argument('authorization'), FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) throw new \InvalidArgumentException('Invalid authorization ID.');
        app(McpAuthorization::class)->revoke($id);
        $this->info('MCP authorization revoked.');
        return 0;
    } catch (\Throwable $exception) {
        $this->error('Authorization not revoked. Verify the authorization ID and database availability.');
        return 1;
    }
})->purpose('Revoke MCP access while preserving invocation provenance');
