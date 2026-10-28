<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Authorize ChatGPT · CLSU MISO</title>
<style>body{font:16px/1.6 system-ui,sans-serif;color:#1e3029;background:#f5f8f6;margin:0;padding:24px}main{max-width:600px;margin:48px auto;background:white;border:1px solid #dce3de;border-radius:6px;padding:32px}h1{color:#005b30;line-height:1.3}code{overflow-wrap:anywhere}button{font:inherit;min-height:44px;border:1px solid #008447;border-radius:6px;background:#008447;color:white;padding:10px 20px;cursor:pointer}form{display:inline-block;margin:12px 12px 0 0}a{color:#005b30}button:focus-visible,a:focus-visible{outline:3px solid #bd8e1a;outline-offset:3px}.cancel{background:white;color:#005b30}</style></head>
<body><main><p>CLSU · MISO Service Portal</p><h1>Allow {{ $client->name }} to read your MISO records?</h1>
<p>Signed in as <strong>{{ $user->name }}</strong>. Access stays within your account and handling office. This connection cannot change requests or complete work.</p>
<p>The selected client will be able to use:</p><ul>
@foreach(\Illuminate\Support\Facades\DB::table('mcp_oauth_client_scopes')->where('client_id', $client->id)->pluck('tool_scope') as $tool)<li><code>{{ $tool }}</code></li>@endforeach
</ul><p>Record text may be sent to the connected provider when you use its tools. You can revoke this client in Assistant settings.</p>
<form method="post" action="{{ route('passport.authorizations.approve') }}">@csrf<input type="hidden" name="auth_token" value="{{ $authToken }}"><button>Allow read access</button></form>
<form method="post" action="{{ route('passport.authorizations.deny') }}">@csrf @method('DELETE')<input type="hidden" name="auth_token" value="{{ $authToken }}"><button class="cancel">Cancel connection</button></form>
</main></body></html>
