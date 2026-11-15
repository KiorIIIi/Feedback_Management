<?php
namespace App\Http\Controllers;

use App\Integrations\Mcp\{AssistantSettings, McpAuthorization, OAuthConnections};
use App\Integrations\Mcp\LocalAssistant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssistantController
{
    public function keys(Request $r)
    {
        app(McpAuthorization::class)->assertAdministrator($r->user());
        $keys = \Illuminate\Support\Facades\DB::table('mcp_authorizations as a')
            ->join('personal_access_tokens as t', 't.id', '=', 'a.personal_access_token_id')
            ->where('a.user_id', $r->user()->id)->where('a.handling_office_id', $r->user()->office_id)
            ->get(['a.id', 'a.client_name', 'a.revoked_at', 't.expires_at']);
        return $this->response(['keys' => $keys]);
    }

    public function createKey(Request $r, AssistantSettings $settings, McpAuthorization $auth)
    {
        $data = $r->validate(['password' => 'required|string|max:255', 'name' => 'required|string|max:100', 'hours' => 'required|integer|min:1|max:168', 'tools' => 'required|array|min:1|max:5', 'tools.*' => ['required', 'distinct', Rule::in(McpAuthorization::TOOLS)]]);
        $settings->confirm($r->user(), $data['password']);
        abort_unless($settings->enabled(), 422, 'Enable MCP before generating a key.');
        $key = \Illuminate\Support\Facades\DB::transaction(function () use ($r, $settings, $auth, $data) {
            $key = $auth->issue($r->user(), $data['name'], $data['tools'], $data['hours']);
            $settings->audit($r->user(), 'assistant.key_created', ['authorization_id' => $key['id'], 'tools' => $data['tools']]);
            return $key;
        });
        return $this->response($key);
    }

    public function revokeKey(Request $r, int $id, AssistantSettings $settings, McpAuthorization $auth)
    {
        $data = $r->validate(['password' => 'required|string|max:255']);
        $settings->confirm($r->user(), $data['password']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($r, $id, $settings, $auth) {
            abort_unless(\Illuminate\Support\Facades\DB::table('mcp_authorizations')->where('id', $id)->where('user_id', $r->user()->id)->where('handling_office_id', $r->user()->office_id)->whereNotNull('personal_access_token_id')->lockForUpdate()->exists(), 404);
            $auth->revoke($id);
            $settings->audit($r->user(), 'assistant.key_revoked', ['authorization_id' => $id]);
        });
        return $this->response(['message' => 'API key revoked.']);
    }

    private function response(array $data) { return response()->json($data)->header('Cache-Control', 'private, no-store'); }

    public function status(Request $r, AssistantSettings $settings, OAuthConnections $oauth)
    {
        return $this->response([...$settings->status($r->user()), 'clients' => $oauth->clients($r->user())]);
    }

    public function save(Request $r, AssistantSettings $settings)
    {
        app(McpAuthorization::class)->assertAdministrator($r->user());
        $data = $r->validate(['password' => 'required|string|max:255', 'expected_version' => 'required|integer|min:1', 'mcp_enabled' => 'required|boolean', 'assistant_enabled' => 'required|boolean', 'model' => ['present', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9._:-]*$/D'], 'api_key' => ['nullable', 'string', 'min:16', 'max:1024', 'regex:/^[!-~]+$/D'], 'remove_key' => 'sometimes|boolean']);
        $data['model'] = $data['model'] ?? '';
        return $this->response($settings->save($r->user(), $data));
    }

    public function createClient(Request $r, AssistantSettings $settings, OAuthConnections $oauth)
    {
        app(McpAuthorization::class)->assertAdministrator($r->user());
        $data = $r->validate(['password' => 'required|string|max:255', 'name' => 'required|string|max:100', 'redirect_uri' => ['required', 'string', 'max:1024', 'regex:#^https://chatgpt\.com/(connector/oauth/[A-Za-z0-9_-]+|connector_platform_oauth_redirect)$#D'], 'tools' => 'required|array|min:1|max:5', 'tools.*' => ['required', 'distinct', Rule::in(McpAuthorization::TOOLS)]]);
        $settings->confirm($r->user(), $data['password']);
        return $this->response($oauth->create($r->user(), $data['name'], $data['redirect_uri'], $data['tools']));
    }

    public function revokeClient(Request $r, string $id, AssistantSettings $settings, OAuthConnections $oauth)
    {
        $data = $r->validate(['password' => 'required|string|max:255']);
        $settings->confirm($r->user(), $data['password']); $oauth->revoke($r->user(), $id);
        return $this->response(['message' => 'Client access revoked.']);
    }

    public function test(Request $r)
    {
        app(McpAuthorization::class)->assertAdministrator($r->user());
        abort(409, 'Provider calls are disabled. Use the local demo read tools instead.');
    }

    public function chat(Request $r)
    {
        app(McpAuthorization::class)->assertAdministrator($r->user());
        abort(409, 'Provider calls are disabled. Your data stays in this system.');
    }

    public function localRead(Request $r, LocalAssistant $assistant)
    {
        app(McpAuthorization::class)->assertAdministrator($r->user());
        $data = $r->validate(['tool' => ['required', Rule::in(McpAuthorization::TOOLS)], 'arguments' => 'sometimes|array']);
        return $this->response($assistant->read($r->user(), $data['tool'], $data['arguments'] ?? []));
    }
}
