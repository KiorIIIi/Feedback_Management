<?php
namespace App\Http\Middleware;

use App\Integrations\Mcp\AssistantSettings;
use App\Integrations\Mcp\McpAuthorization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrepareMcpOAuth
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(AssistantSettings::class);
        abort_unless($settings->enabled(), 404);
        abort_unless(app()->environment(['local', 'testing']) || $request->isSecure(), 403);
        $settings->configureOAuth();
        if ($request->is('.well-known/*')) return $next($request);
        $resource = rtrim(config('app.url'), '/').'/api/mcp';
        if ($request->is('oauth/token') || $request->isMethod('GET')) {
            // Resource binding is persisted per registered client, checked at exchange and use.
            abort_unless($request->input('resource') === $resource, 400, 'The OAuth resource must match this MCP endpoint.');
            $id = $request->input('client_id');
            abort_unless(is_string($id) && \Illuminate\Support\Str::isUuid($id), 400, 'Supply a registered OAuth Client ID.');
            $client = DB::table('mcp_oauth_clients')->join('oauth_clients', 'oauth_clients.id', '=', 'mcp_oauth_clients.client_id')
                ->where('client_id', $id)->where('resource', $resource)->where('revoked', false)->first();
            abort_unless($client, 400, 'Register this OAuth client in admin setup first.');
            if ($request->is('oauth/token')) {
                abort_unless(in_array($request->input('grant_type'), ['authorization_code', 'refresh_token'], true), 400);
            } else {
                if (!$request->user()) return redirect('/login?role=staff&return_to='.rawurlencode('/oauth/authorize?'.$request->getQueryString()));
                app(McpAuthorization::class)->assertAdministrator($request->user());
                abort_unless((int) $client->user_id === $request->user()->id && (int) $client->handling_office_id === (int) $request->user()->office_id, 403);
                abort_unless($request->input('code_challenge_method') === 'S256' && preg_match('/^[A-Za-z0-9_-]{43}$/D', (string) $request->input('code_challenge')) && $request->input('scope') === 'mcp:use', 400, 'Use authorization code with S256 PKCE and the mcp:use scope.');
                $request->merge(['prompt' => 'consent']);
                $request->session()->put('mcp_oauth_client', $id);
            }
        } else {
            app(McpAuthorization::class)->assertAdministrator($request->user());
            $id = $request->session()->get('mcp_oauth_client');
            abort_unless(DB::table('mcp_oauth_clients')->join('oauth_clients', 'oauth_clients.id', '=', 'mcp_oauth_clients.client_id')->where('client_id', $id)->where('resource', $resource)->where('revoked', false)->where('user_id', $request->user()->id)->where('handling_office_id', $request->user()->office_id)->exists(), 403);
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
