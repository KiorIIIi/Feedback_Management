<?php
namespace Tests\Feature;

use App\Models\User;
use App\Integrations\Mcp\AssistantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssistantSetupTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(); }
    private function admin(): User { return User::where('email', 'admin@clsu.demo')->firstOrFail(); }
    private function enable(): void
    {
        $this->actingAs($this->admin())->putJson('/api/assistant/settings', ['password' => 'DemoPass!2026', 'expected_version' => 1, 'mcp_enabled' => true, 'assistant_enabled' => false, 'model' => ''])->assertOk()->assertJsonPath('mcp_enabled', true);
    }
    private function client(array $tools = ['search_requests']): array
    {
        return $this->postJson('/api/assistant/clients', ['password' => 'DemoPass!2026', 'name' => 'Synthetic ChatGPT', 'redirect_uri' => 'https://chatgpt.com/connector/oauth/synthetic_callback', 'tools' => $tools])->assertOk()->json();
    }
    private function authorizeUrl(string $id, string $verifier): string
    {
        return '/oauth/authorize?'.http_build_query(['client_id' => $id, 'redirect_uri' => 'https://chatgpt.com/connector/oauth/synthetic_callback', 'response_type' => 'code', 'scope' => 'mcp:use', 'state' => 'synthetic-state', 'resource' => rtrim(config('app.url'), '/').'/api/mcp', 'code_challenge_method' => 'S256', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')]);
    }
    public function test_setup_is_admin_only_and_secrets_are_not_returned_or_audited(): void
    {
        $this->getJson('/api/assistant/settings')->assertUnauthorized();
        foreach (['andrea@clsu.demo', 'mark@clsu.demo'] as $email) $this->actingAs(User::where('email', $email)->firstOrFail())->getJson('/api/assistant/settings')->assertForbidden();
        $this->actingAs($this->admin());
        $body = ['password' => 'wrong', 'expected_version' => 1, 'mcp_enabled' => true, 'assistant_enabled' => false, 'model' => 'synthetic-model', 'api_key' => 'synthetic-provider-key-123456789'];
        $this->putJson('/api/assistant/settings', $body)->assertUnprocessable();
        $body['password'] = 'DemoPass!2026';
        $response = $this->putJson('/api/assistant/settings', $body)->assertOk()->assertJsonPath('key_configured', true);
        $this->assertStringNotContainsString($body['api_key'], $response->getContent());
        $this->assertNotSame($body['api_key'], DB::table('assistant_settings')->value('provider_api_key'));
        $this->assertStringNotContainsString($body['api_key'], DB::table('audit_entries')->get()->toJson());
        $this->putJson('/api/assistant/settings', $body)->assertConflict();
        $body['expected_version'] = 2; $body['assistant_enabled'] = true;
        $this->putJson('/api/assistant/settings', $body)->assertUnprocessable()->assertJsonValidationErrors('assistant_enabled');
        $body['assistant_enabled'] = false;
        config(['mcp.admin_setup_allowed' => false, 'mcp.enabled' => false]);
        $this->assertFalse(app(AssistantSettings::class)->enabled());
        $body['expected_version'] = 2;
        $this->putJson('/api/assistant/settings', $body)->assertForbidden();
    }
    public function test_oauth_code_pkce_discovery_tool_scope_and_revocation(): void
    {
        $this->enable(); $client = $this->client();
        $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->assertJsonPath('code_challenge_methods_supported.0', 'S256')->assertJsonMissingPath('registration_endpoint');
        $this->getJson('/.well-known/oauth-protected-resource/api/mcp')->assertOk()->assertJsonPath('resource', rtrim(config('app.url'), '/').'/api/mcp');
        $verifier = str_repeat('a', 64);
        $this->get($this->authorizeUrl($client['id'], $verifier))->assertOk()->assertSee('Allow read access');
        $authToken = session('authToken');
        $approved = $this->post('/oauth/authorize', ['auth_token' => $authToken])->assertRedirect();
        parse_str(parse_url($approved->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('synthetic-state', $query['state']);
        $exchange = ['grant_type' => 'authorization_code', 'client_id' => $client['id'], 'redirect_uri' => $client['redirect_uri'], 'resource' => rtrim(config('app.url'), '/').'/api/mcp', 'code' => $query['code'], 'code_verifier' => $verifier];
        $token = $this->postJson('/oauth/token', $exchange)->assertOk()->json('access_token');
        $rpc = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'MCP-Protocol-Version' => '2025-11-25'])->postJson('/api/mcp', $rpc)->assertOk()->assertJsonPath('result.tools.0.name', 'search_requests')->assertJsonCount(1, 'result.tools');
        $rpc['method'] = 'tools/call'; $rpc['params'] = ['name' => 'search_requests', 'arguments' => (object) []];
        $this->postJson('/api/mcp', $rpc)->assertOk()->assertJsonPath('result.isError', false);
        $this->assertDatabaseHas('mcp_invocations', ['tool_name' => 'search_requests', 'outcome' => 'successful']);
        $this->deleteJson('/api/assistant/clients/'.$client['id'], ['password' => 'DemoPass!2026'])->assertOk();
        $this->postJson('/api/mcp', $rpc)->assertUnauthorized();
        $this->assertDatabaseHas('oauth_access_tokens', ['revoked' => true]);
        $this->postJson('/oauth/token', $exchange)->assertStatus(400);
    }
    public function test_oauth_rejects_foreign_callback_resource_account_and_plain_pkce(): void
    {
        $this->enable(); $client = $this->client();
        $this->postJson('/api/assistant/clients', ['password' => 'DemoPass!2026', 'name' => 'Bad client', 'redirect_uri' => 'https://chatgpt.com.evil.example/connector/oauth/id', 'tools' => ['search_requests']])->assertUnprocessable();
        $url = $this->authorizeUrl($client['id'], str_repeat('a', 64));
        $this->get(str_replace('S256', 'plain', $url))->assertStatus(400);
        $this->get(str_replace(rawurlencode(rtrim(config('app.url'), '/').'/api/mcp'), rawurlencode('https://other.example/api/mcp'), $url))->assertStatus(400);
        $other = $this->admin()->replicate(); $other->email = 'synthetic-second-admin@example.test'; $other->save();
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->deleteJson('/api/assistant/clients/'.$client['id'], ['password' => 'DemoPass!2026'])->assertNotFound();
    }
}
