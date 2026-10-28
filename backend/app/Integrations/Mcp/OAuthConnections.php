<?php
namespace App\Integrations\Mcp;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;

class OAuthConnections
{
    public function create(User $user, string $name, string $redirect, array $tools): array
    {
        app(McpAuthorization::class)->assertAdministrator($user);
        abort_unless(app(AssistantSettings::class)->enabled(), 422, 'Enable MCP before registering a client.');
        return DB::transaction(function () use ($user, $name, $redirect, $tools) {
            $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient($name, [$redirect], confidential: false);
            $resource = rtrim(config('app.url'), '/').'/api/mcp';
            DB::table('mcp_oauth_clients')->insert(['client_id' => $client->id, 'user_id' => $user->id, 'handling_office_id' => $user->office_id, 'resource' => $resource, 'created_at' => now()]);
            DB::table('mcp_oauth_client_scopes')->insert(array_map(fn ($tool) => ['client_id' => $client->id, 'tool_scope' => $tool], array_unique($tools)));
            app(AssistantSettings::class)->audit($user, 'assistant.oauth_client_created', ['client_id' => $client->id, 'tools' => array_values(array_unique($tools))]);
            return ['id' => $client->id, 'name' => $name, 'redirect_uri' => $redirect, 'resource' => $resource, 'tools' => $tools, 'revoked' => false];
        });
    }

    public function clients(User $user): array
    {
        return DB::table('mcp_oauth_clients')->join('oauth_clients', 'oauth_clients.id', '=', 'mcp_oauth_clients.client_id')
            ->where('user_id', $user->id)->where('handling_office_id', $user->office_id)->orderByDesc('mcp_oauth_clients.created_at')
            ->get(['oauth_clients.id', 'name', 'revoked', 'redirect_uris'])->map(fn ($client) => [
                'id' => $client->id, 'name' => $client->name, 'revoked' => (bool) $client->revoked,
                'redirect_uri' => json_decode($client->redirect_uris, true)[0],
                'tools' => DB::table('mcp_oauth_client_scopes')->where('client_id', $client->id)->pluck('tool_scope')->all(),
            ])->all();
    }

    public function revoke(User $user, string $id): void
    {
        DB::transaction(function () use ($user, $id) {
            abort_unless(DB::table('mcp_oauth_clients')->where('client_id', $id)->where('user_id', $user->id)->where('handling_office_id', $user->office_id)->lockForUpdate()->exists(), 404);
            DB::table('oauth_clients')->where('id', $id)->update(['revoked' => true]);
            $tokens = DB::table('oauth_access_tokens')->where('client_id', $id)->pluck('id');
            DB::table('oauth_access_tokens')->whereIn('id', $tokens)->update(['revoked' => true]);
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokens)->update(['revoked' => true]);
            DB::table('oauth_auth_codes')->where('client_id', $id)->update(['revoked' => true]);
            DB::table('mcp_authorizations')->whereIn('oauth_access_token_id', $tokens)->update(['revoked_at' => now()]);
            app(AssistantSettings::class)->audit($user, 'assistant.oauth_client_revoked', ['client_id' => $id]);
        });
    }

    public function authenticate(Request $request): object
    {
        abort_unless(app(AssistantSettings::class)->row()->oauth_private_key, 401);
        app(AssistantSettings::class)->configureOAuth();
        // Use Passport's bearer validation directly; session/cookie fallback cannot grant MCP.
        $guard = auth()->guard('mcp-oauth');
        $guard->forgetUser();
        $oauthUser = $guard->setRequest($request)->user();
        abort_unless($oauthUser && $oauthUser->currentAccessToken()?->can('mcp:use'), 401);
        $tokenId = $oauthUser->currentAccessToken()->id;
        $user = User::findOrFail($oauthUser->getAuthIdentifier());
        app(McpAuthorization::class)->assertAdministrator($user);
        return DB::transaction(function () use ($user, $tokenId) {
            $token = DB::table('oauth_access_tokens')->where('id', $tokenId)->lockForUpdate()->first();
            $client = $token ? DB::table('mcp_oauth_clients')->where('client_id', $token->client_id)->first() : null;
            abort_unless($token && !$token->revoked && $client && (int) $client->user_id === $user->id && (int) $client->handling_office_id === (int) $user->office_id && $client->resource === rtrim(config('app.url'), '/').'/api/mcp', 401);
            $grant = DB::table('mcp_authorizations')->where('oauth_access_token_id', $tokenId)->first();
            if (!$grant) {
                $name = DB::table('oauth_clients')->where('id', $token->client_id)->value('name');
                $id = DB::table('mcp_authorizations')->insertGetId(['user_id' => $user->id, 'handling_office_id' => $user->office_id, 'oauth_access_token_id' => $tokenId, 'client_name' => $name, 'client_kind' => 'oauth', 'created_at' => now()]);
                $tools = DB::table('mcp_oauth_client_scopes')->where('client_id', $token->client_id)->pluck('tool_scope');
                DB::table('mcp_authorization_scopes')->insert($tools->map(fn ($tool) => ['authorization_id' => $id, 'tool_scope' => $tool])->all());
                $grant = DB::table('mcp_authorizations')->find($id);
            }
            abort_if($grant->revoked_at || (int) $grant->user_id !== $user->id || (int) $grant->handling_office_id !== (int) $user->office_id, 401);
            $grant->user = $user;
            return $grant;
        });
    }
}
