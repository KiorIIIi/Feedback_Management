# MCP implementation — read-only pilot

Implemented foundation, 23 October 2026. This is the first delivery against the [planned MCP catalog](system-architecture.md#mcp-connection-and-agent-capabilities), not the complete operational-agent release. No model provider, embedded chat, external client connection, deployment or MISO policy acceptance is claimed.

## Selected stack and transport

The existing PHP 8.5.12 / Laravel 12.70.0 application now pins `laravel/mcp` **1.1.1** and `laravel/sanctum` **4.3.3** in Composer, with exact transitive versions in `backend/composer.lock`. Installing these packages did not update other dependencies. Compatibility was checked against the installed framework and the official [MCP package requirements](https://github.com/laravel/mcp/blob/v1.1.1/composer.json), [Sanctum package requirements](https://github.com/laravel/sanctum/blob/v4.3.3/composer.json) and [Laravel MCP documentation](https://laravel.com/framework/docs/12.x/mcp).

The stateless HTTP endpoint is **POST `/api/mcp`**. Route registration is stable for route caching. `MCP_ENABLED=false` is the default; middleware returns 404 while disabled, including for the endpoint's GET/DELETE routes. There is no stdio registration that bypasses authentication. The server supports protocol versions `2026-07-28`, `2025-11-25` and `2025-06-18` through the official package. Legacy clients use initialization; current protocol clients use discovery and the matching method/name/version headers and metadata required by that package. GET and DELETE are not supported by this stateless transport.

## Pilot setup

For client-specific instructions, use the [Claude, ChatGPT and Gemini connection guide](../project/mcp-client-connection-guide.md). It distinguishes bearer-capable pilot clients from consumer-app connections that need OAuth or account/region eligibility.

Use a development or separately authorized pilot environment. Apply additive migrations; never reseed populated data:

```powershell
composer install --working-dir=backend
php backend/artisan migrate
```

Set `MCP_ENABLED=true` in that environment's backend configuration and restart its PHP runtime. Clear/rebuild cached configuration as appropriate. The local development client connects directly to `http://127.0.0.1:8000/api/mcp`; Vite also proxies `/api/mcp`. Remote environments require HTTPS as determined through the existing trusted-proxy configuration. Public-demo runtime/configuration is separate; this implementation does not enable or deploy MCP there.

A trusted server operator issues an expiring credential for an **active MISO administrator**, selecting each tool explicitly. Replace `1` with the intended synthetic administrator's actual user ID:

```powershell
php backend/artisan mcp:issue 1 --client="Local pilot client" --tool=search_requests --tool=get_request_details --tool=get_personnel_workload --tool=get_feedback_metrics --tool=get_report_job --hours=24
php backend/artisan mcp:revoke 1
```

The second command revokes the authorization ID printed by issuance; it is not a user ID. Issuance prints the raw credential once to the trusted operator terminal. Store it in the client's secret store, never in frontend code, source control, chat, screenshots or logs. Use `Authorization: Bearer <credential>` on every MCP request. Lifetime must be 1–168 hours; the default is 24 hours. CLI commands are trusted operator administration, never MCP tools or browser endpoints. Issuing a credential does not enable the endpoint.

Cookie authentication is deliberately insufficient for MCP. MCP credentials cannot authenticate ordinary session API routes, create reports, or change requests. External multi-user OAuth and credential renewal are future work. This pilot requires a compatible client that can supply a bearer header; no particular external client is certified by the automated tests.

Example legacy tool call after initialization, with `Content-Type: application/json`, `Accept: application/json`, a bearer header and `MCP-Protocol-Version: 2025-11-25`:

```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"search_requests","arguments":{"status":"Pending","page":1}}}
```

## Delivered tools

| Tool | Inputs and behavior |
| --- | --- |
| `search_requests` | Optional literal subject/job substring, status, priority, active personnel filter and page; 20 results/page, minimal fields and current record versions. |
| `get_request_details` | Request ID and optional history page; authorized details plus 50 history entries/page, including administrator-only internal entries. Descriptions/history are marked untrusted content. |
| `get_personnel_workload` | Optional service-type eligibility filter and page; active personnel, eligibility IDs and scoped active-assignment counts, 20 people/page. Recorded availability is explicitly unknown. |
| `get_feedback_metrics` | Optional 1–366 calendar days, default 30; existing backend service metrics, verified evaluation counts and period/denominator definitions. General feedback remains separate. |
| `get_report_job` | Owned, office-authorized report-job ID; pending/processing/completed/failed status. A download URL appears only for a successful existing private file at the expected path; using it requires a separately authenticated portal browser session. |

Each tool is individually granted and checked at execution. The advertised catalog contains only granted tools. Unknown arguments are rejected; callers cannot supply authorizing identities or office overrides. Pagination is bounded and request histories are paginated. Tool execution returns `state: successful` or an MCP error with `state: failed` and `invalid_input`, `not_found`, `permission_denied` or `temporary_failure`. A successful read of a pending or failed report does not mean the report succeeded.

API and MCP share `RequestAccess`, `RequestReader`, `PersonnelWorkload`, `ReportingService` and `ReportJobReader`. Search now treats `%` and `_` literally in both interfaces. Requester ownership, personnel restrictions, handling-office scope and report ownership remain server rules. MCP response projections omit requester identity/contact fields, raw file paths, evaluations and unneeded audit metadata; request text can still contain personal data and must follow the agreed provider-handling policy before connection to an external model.

## Authorization, storage and audit consistency

Migration `2026_10_23_000300_create_mcp_foundation` adds:

- Sanctum's standard `personal_access_tokens` structure, storing SHA-256 credential hashes and mandatory pilot expiry.
- `mcp_authorizations`, binding a stable client label, user, token and handling office. The unique token reference prevents a credential from authenticating different grants. Issuance fixes identity and office; no editing/renewal route is provided.
- `mcp_tool_scopes` and composite-key `mcp_authorization_scopes`, providing individually managed memberships with foreign keys. Sanctum JSON abilities are intentionally empty and never grant tool permissions.
- `mcp_invocations`, deriving client/user identity through the authorization rather than repeating those facts. It retains tool, start/finish timestamps, execution outcome, safe error code and typed references for successfully authorized detail/report reads. Unknown tool names are recorded as `unknown`. Parameters, raw credentials, record text and response payloads are not audited.

The framework's polymorphic token owner and the authorization's user reference intentionally overlap at the authentication boundary. Issuance commits them together; authentication checks that the token resolves to the same `User`, that the user is still an active MISO administrator, and that the current office equals the grant's fixed office. A mismatch fails closed. Office membership alone grants no access. Scope removal takes effect on subsequent requests. Revocation expires the credential and marks the grant revoked transactionally while preserving its identity rows and audit relationships. Retain those rows rather than deleting tokens through generic token-pruning administration; retention/purging needs a future explicit policy.

Authenticated tool calls create a `processing` invocation before authorization/dispatch and finish as `successful` or `failed`. Scope denials, unsupported tools, validation errors, missing/out-of-scope records and protocol failures retain outcomes. Failed connection authentication has no trusted grant and does not create an attributed tool invocation. Process termination can leave `processing` evidence, which is not proof of success. Auditing must succeed before record content is returned. A 120/minute IP-level transport limit and 60/minute authorization limit apply. Untrusted browser origins are rejected, and authenticated transport responses use private/no-store caching.

The existing physical priority schema already separates version-wide effective dates into `priority_rule_versions` and exact matrix entries into `priority_rules`; this change does not modify priority policy. Relational MCP memberships resolve the affected JSON-scope proposal, and derived audit provenance resolves repeated MCP audit identity facts for this milestone. The logical ERD remains a proposal, and this is not a certification that the whole schema is 3NF.

## Remaining planned work

The attention queue awaits an explicit ranking/deadline policy; no deadline or availability is invented. Operational tools (`assign_request`, priority changes, internal notes, clarification, non-completion status transitions and report creation) and `get_operation_result` are not registered. Before enabling writes, implement persistent caller/tool-scoped operation identifiers, canonical fingerprints, atomic outcomes/domain-history/audit commits, permission-checked outcome retrieval, stale-version responses and bound single-use approvals for any action configured to require them. Completion remains human-only. Unattended bulk actions, arbitrary SQL/shell, account/configuration administration and arbitrary email remain excluded.

The embedded assistant stays disconnected. Provider/runtime selection, external multi-user OAuth, provider data-handling agreement, action confirmation policy, deployment, worker supervision and real-client integration checks remain separate work. Ordinary portal workflows continue without MCP or a provider.

## Verification

`backend/tests/Feature/McpFoundationTest.php` exercises the real HTTP endpoint, credential lifecycle, per-tool catalog/denials, identity/office changes, literal search, minimal fields, bounded history, workload/metrics, private report status and session/API isolation. See the [Iteration 03 / Sprint 03 change record](../changes/iteration-03/sprint-03/001-mcp-read-foundation.md) for actual commands, results and remaining limitations.

[Architecture index](README.md) · [System architecture](system-architecture.md) · [Implementation notes](../project/implementation-notes.md)
