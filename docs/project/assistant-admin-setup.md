# Admin assistant and MCP setup

Implemented setup interface, 25 October 2026, Asia/Manila. Open **MCP Assistant → Connection settings**, or **Management → Assistant settings** at `/app/assistant/settings`. Only active MISO administrators can use the page or its API. This supersedes the earlier reserved assistant screen.

## Admin controls

- Enable or disable the external MCP server. A nullable initial setting preserves the existing operator `MCP_ENABLED` choice until an administrator explicitly saves a choice.
- Save an OpenAI model ID and an encrypted, server-side API key. The saved key and OAuth private signing key are never returned to the browser or included in audit payloads. Key removal is explicit.
- Register a ChatGPT OAuth client with an exact callback URL and selected read tools. Clients belong to the signing-in administrator and the current handling office. An administrator cannot register or revoke another administrator's clients.
- Revoke a client, including its access tokens, refresh tokens, pending authorization codes and associated MCP grants. Historical identity and invocation records are retained.
- See the canonical endpoint, whether HTTPS is configured, and the last successful external tool invocation for the current account. Registration or server enablement is not reported as an established ChatGPT connection.

Saving configuration and creating/revoking clients require the current account password, trusted origin and rate-limited session authentication. Configuration saves check an expected record version and commit the change and minimal audit event together. Existing service workflows and live notifications remain independent.

Admin setup is allowed by default only in `local` and `testing`. Other environments require the hosting operator to set `MCP_ADMIN_SETUP_ALLOWED=true`; otherwise the existing operator enablement flag remains authoritative and the UI is read-only. The temporary public demo keeps MCP disabled and does not opt into admin setup. This change does not deploy a public endpoint or start a tunnel.

## Connect ChatGPT web

1. Arrange an authorized reachable HTTPS endpoint, with its canonical origin configured as `APP_URL`. Route `/api`, `/oauth` and the documented OAuth `/.well-known` metadata paths to Laravel. A loopback address works for local clients, not ChatGPT's remote service. Vite proxies those paths for local development; the public router permits only the specific OAuth metadata paths through its dotfile guard.
2. Enable and save the MCP server from admin setup. This creates the encrypted signing key if needed.
3. In ChatGPT, add a custom MCP server with the displayed `/api/mcp` address and OAuth authentication. Copy the exact callback URL shown for that connection.
4. Register that callback and the desired read tools in the portal. Only the documented HTTPS `chatgpt.com` callback paths are accepted; lookalike hosts, query strings, fragments and arbitrary redirects are rejected.
5. Copy the resulting **OAuth Client ID** into ChatGPT. Leave the client secret empty: this is a pre-registered public client using S256 PKCE. Complete CLSU sign-in and the explicit read-access consent screen.
6. Invoke a granted read tool and check the last successful external call. No code change or synthetic local test establishes that an actual ChatGPT account completed this step.

The implementation uses pinned Laravel Passport 13.10.0 and Laravel MCP 1.1.1. Only authorization-code and refresh exchanges are exposed. Dynamic registration, password/device/client-credentials flows and account/configuration administration through MCP are absent. Access tokens last one hour and refresh tokens one day. An exchanged `resource` must exactly match the client's stored canonical MCP URL; each tool invocation checks the same binding, current active administrator, fixed handling office and individual tool grants. Passport validates the signed access token and its client audience; the separate immutable client/resource binding restricts that credential to this MCP resource.

Official sources: [ChatGPT OAuth authentication](https://developers.openai.com/plugins/build/auth), [custom MCP setup](https://developers.openai.com/api/docs/guides/custom-mcp-server), [Laravel MCP authentication](https://laravel.com/framework/docs/12.x/mcp#authentication), [Passport](https://laravel.com/framework/docs/12.x/passport). Account/workspace eligibility and actual remote interoperability remain separate checks.

## Embedded assistant boundary

The administrator requested both external ChatGPT and an embedded assistant. The setup screen can store the model/key securely, and the conversation interface is present. **Provider execution is disabled pending explicit approval for sending messages and authorized record results to `api.openai.com`.** Server validation rejects enabling embedded execution, and the send/test controls remain disabled. No provider transport or live provider call is included in this delivery. This is an unresolved authorization requirement, not a passing model-connection test.

The requested approval distinguishes synthetic demo data from real campus data. Do not infer consent from a saved key, server enablement, elapsed time, submitted record text or an external MCP connection. An OpenAI API project/key is separate from signing into ChatGPT.

## Physical schema and runtime

Migration `2026_10_25_000500_create_assistant_setup` adds Passport authorization-code/client/access/refresh structures, singleton integration settings, office/user-bound client registrations and a relational client/tool junction. Existing MCP grants reference exactly one Sanctum credential or Passport access token. MySQL enforces the exclusive credential check; SQLite enforces equivalent insert/update triggers. Existing invocation identity still derives through its grant. The settings version/update identity and encrypted keys are configuration facts; audit payloads deliberately retain only historical non-secret changes.

The separate Passport model maps to the existing users table without replacing session or Sanctum behavior. On every bearer request the validated OAuth identity is resolved back to the ordinary domain user before shared authorization and read services run. Client registrations are immutable except revocation; a changed canonical origin requires new client registration. Rotating `APP_KEY` requires reconfiguration of encrypted integration secrets; do not discard the old key without a planned rotation.

PHP requires the Sodium extension for the selected JWT/OAuth dependencies. On this Windows machine its installed extension was enabled in the patched PHP 8.5 runtime. Initial RSA generation uses Passport's phpseclib approach with PHP's bundled OpenSSL configuration on Windows. Dependencies remain locked; no advisory suppression or platform-requirement bypass is used.

[Connection guide](mcp-client-connection-guide.md) · [MCP implementation](../architecture/mcp-implementation.md) · [Change record](../changes/iteration-03/sprint-03/006-admin-assistant-setup.md)
