<?php
namespace App\Http\Controllers;

use App\Integrations\Mcp\{AssistantSettings, McpAuthorization, OAuthConnections};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssistantController
{
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
}
