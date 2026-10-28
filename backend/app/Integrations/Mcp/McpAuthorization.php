<?php

namespace App\Integrations\Mcp;

use App\Models\User;
use App\Services\RequestAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class McpAuthorization
{
    public const TOOLS = [
        'search_requests', 'get_request_details', 'get_personnel_workload',
        'get_feedback_metrics', 'get_report_job',
    ];

    public function assertAdministrator(User $user): void
    {
        app(RequestAccess::class)->admin($user);
        abort_unless(DB::table('offices')->where('id', $user->office_id)->value('office_code') === 'MISO', 403);
    }

    public function issue(User $user, string $client, array $tools, int $hours = 24): array
    {
        $this->assertAdministrator($user);
        if (!trim($client) || mb_strlen($client) > 100 || !$tools || array_diff($tools, self::TOOLS) || $hours < 1 || $hours > 168) {
            throw ValidationException::withMessages(['authorization' => 'Supply a client name, explicit supported read tools, and a lifetime of 1–168 hours.']);
        }
        return DB::transaction(function () use ($user, $client, $tools, $hours) {
            // Sanctum abilities are intentionally empty: relational memberships are authoritative.
            $token = $user->createToken('mcp:'.trim($client), [], now()->addHours($hours));
            $id = DB::table('mcp_authorizations')->insertGetId([
                'user_id' => $user->id, 'personal_access_token_id' => $token->accessToken->id,
                'handling_office_id' => $user->office_id, 'client_name' => trim($client), 'created_at' => now(),
            ]);
            DB::table('mcp_authorization_scopes')->insert(array_map(
                fn ($tool) => ['authorization_id' => $id, 'tool_scope' => $tool], array_values(array_unique($tools))
            ));
            return ['id' => $id, 'token' => $token->plainTextToken, 'expires_at' => $token->accessToken->expires_at->toIso8601String()];
        });
    }

    public function authenticate(?string $bearer): object
    {
        abort_unless($bearer, 401, 'An MCP bearer credential is required.');
        $token = PersonalAccessToken::findToken($bearer);
        abort_unless($token && $token->expires_at && $token->expires_at->isFuture(), 401, 'Invalid or expired MCP credential.');
        $grant = DB::table('mcp_authorizations')->where('personal_access_token_id', $token->id)->whereNull('revoked_at')->first();
        abort_unless($grant, 401, 'Invalid or revoked MCP authorization.');
        $user = $token->tokenable;
        // Preserve the identity bridge to Sanctum, never trusting caller-supplied IDs.
        abort_unless($user instanceof User && (int) $grant->user_id === $user->id, 401);
        $this->assertAdministrator($user);
        abort_unless((int) $grant->handling_office_id === (int) $user->office_id, 403, 'The authorized office has changed.');
        $grant->user = $user;
        return $grant;
    }

    public function permits(object $grant, string $tool): bool
    {
        return in_array($tool, self::TOOLS, true) && DB::table('mcp_authorization_scopes')
            ->where('authorization_id', $grant->id)->where('tool_scope', $tool)->exists();
    }

    public function revoke(int $id): void
    {
        DB::transaction(function () use ($id) {
            $grant = DB::table('mcp_authorizations')->where('id', $id)->lockForUpdate()->first();
            abort_unless($grant, 404);
            DB::table('mcp_authorizations')->where('id', $id)->update(['revoked_at' => now()]);
            // Keep credential identity rows for provenance; the raw credential was never stored.
            if ($grant->personal_access_token_id) PersonalAccessToken::whereKey($grant->personal_access_token_id)->update(['expires_at' => now()]);
            if ($grant->oauth_access_token_id ?? null) {
                DB::table('oauth_access_tokens')->where('id', $grant->oauth_access_token_id)->update(['revoked' => true]);
                DB::table('oauth_refresh_tokens')->where('access_token_id', $grant->oauth_access_token_id)->update(['revoked' => true]);
            }
        });
    }
}
