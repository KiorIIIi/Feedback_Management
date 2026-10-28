# Independent security review — 24 October 2026

Scope: read-only review of the CLSU Feedback Management System and Service Tracker on branch `front-main`. The review inspected the current Laravel API, React client, MySQL schema, public-demo launcher, MCP integration, tests, dependencies, and repository configuration. No application code, dependency version, database, deployment, or Git history was changed. The report separates confirmed findings from hardening requirements, deployment requirements, unresolved policy decisions, and unverified hypotheses.

## Assessment

Within this scoped review, no confirmed critical or high-severity remotely exploitable application vulnerability was established. The core authorization, workflow, report, CSRF, SQL-injection, output-escaping, and MCP controls reviewed were implemented consistently.

The highest confirmed issue is a **CI/process gap**: the only workflow is stale, does not run for the active development branch, and would fail if triggered. Several low-severity hardening items remain around public-demo secret files, MCP opt-in parsing, MCP audit recovery, and provisioning cleanup.

This is not a production-penetration test, a full secret-history audit, a MySQL concurrency certification, or a claim of production readiness.

## Confirmed findings

| ID | Severity | Confidence | Type | Finding |
| --- | --- | --- | --- | --- |
| F-01 | Medium | High | CI / verification | CI is non-functional and does not verify the active branch. |
| F-02 | Low | High | Configuration | `MCP_ENABLED` uses a permissive truthy cast; values such as `off` enable MCP. |
| F-03 | Low | High | Local confidentiality | Public-demo runtime/access files are written without explicit restrictive file permissions. |
| F-04 | Low | High | Audit reliability | An interrupted MCP invocation can remain permanently `processing`. |
| F-05 | Low | High | Deployment hygiene | Public-demo provisioning can leave an orphaned database/user with broad grants on failure. |

### F-01 — CI does not protect the active branch and is currently broken

**Evidence:** `.github/workflows/laravel.yml:3-7` triggers only for `main`; `:15-18` selects PHP `8.0`; `:19-26` copies `.env`, installs dependencies, creates directories, and creates a database at the repository root; `:31-35` runs `php artisan test` from the root.

**Why it matters:** `backend/composer.json:5` requires PHP `^8.5`, and the Laravel application, `.env.example`, `artisan`, `storage`, and `database` directory are under `backend/`. The workflow would fail before running tests. More importantly, changes on the active `front-main` branch do not trigger any verification, so security regressions and dependency problems can merge without automated checks. The workflow also has no frontend install/build/test, no `composer audit`, no `npm audit`, no docs check, no least-privilege `permissions`, and uses a mutable `actions/checkout@v4` tag.

**Recommendation:** trigger on both `front-main` and `main`; set the working directory to `backend` for PHP/Composer steps; use PHP 8.5; restore the intended test environment/database setup; add frontend `npm ci`, build, and relevant tests; add `composer audit` and `npm audit`; pin actions to commit SHAs; set `permissions: contents: read`; add `python scripts/check-docs.py`. This is a verification gap, not an application exploit.

### F-02 — `MCP_ENABLED` parsing can turn a nominal “off” value into enabled

**Evidence:** `backend/config/mcp.php:5` uses `(bool) env('MCP_ENABLED', false)`. `backend/app/Http/Middleware/AuthenticateMcp.php:15` treats any truthy config value as enabled. The production guard in `backend/app/Providers/AppServiceProvider.php:8-11` passes only `app`, `session`, `database`, `security`, and `demo_seed` to `ProductionConfiguration::assertSafe()`; `backend/app/Services/ProductionConfiguration.php:10-25` does not inspect MCP enablement.

**Why it matters:** Laravel converts `true`/`false` and related literals, but a value such as `off`, `no`, or `disabled` remains a non-empty string and casts to `true`. An operator who believes MCP is disabled could expose the endpoint. The endpoint still requires a valid, unexpired, unrevoked bearer token for an active MISO administrator, with explicit tool scopes, so this is not an authentication bypass. It is a fail-closed/policy defect.

**Recommendation:** parse the setting strictly, for example with `filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)` and reject invalid values, or require the literal `true`. Add a production-startup rejection or explicit allow-list for MCP enablement. Document accepted values and keep the default disabled.

### F-03 — Public-demo secret files lack explicit restrictive permissions

**Evidence:** `scripts/init-public-demo.php:9` creates support directories with mode `0700`, but `:15` rewrites `runtime.json` and `:58-59` write `access.json` and `runtime.json` with `file_put_contents()` and no explicit file mode or Windows ACL. `runtime.json` contains the database password and `APP_KEY`; `access.json` contains plaintext synthetic demo account passwords.

**Why it matters:** on POSIX systems the parent directory mode helps, but the files themselves inherit the process umask. On Windows, inherited ACLs can allow other local accounts or processes to read `.local/public-host`. The public router correctly serves only `.local/public-host/site` and blocks traversal, so this is not a remote web exposure. It is a local confidentiality/deployment hardening issue.

**Recommendation:** create these files with owner-only permissions (`0600` on POSIX) and an explicit current-user-only ACL on Windows, or use an OS secret store. Consider removing or rotating public-demo credentials when the demo is stopped, and document that the directory is private. Do not place these values in tracked files or logs.

### F-04 — MCP audit rows can remain in `processing`

**Evidence:** `backend/app/Http/Middleware/AuthenticateMcp.php:30-32` inserts a `processing` invocation before dispatch; `:52-58` updates it in `finally`. A hard process termination, fatal crash, or database outage during the call can bypass `finally`.

**Why it matters:** the read is denied or fails, but the audit row remains `processing` indefinitely. This weakens provenance and can mislead operators about whether a tool call completed. It is not an access-control bypass.

**Recommendation:** use a stale-invocation reconciliation command/job that marks old `processing` rows as `failed`/`interrupted`, or add a lease/started-at threshold. Preserve the existing behavior of not storing arguments, results, or credentials.

### F-05 — Public-demo provisioning can leave broad grants or orphaned accounts

**Evidence:** `scripts/init-public-demo.php:41` grants `ALL PRIVILEGES` before migrations/seeding; `:48-49` runs migrations and seeding; `:56-57` revokes and grants only `SELECT, INSERT, UPDATE, DELETE`. There is no `try/finally` cleanup around provisioning.

**Why it matters:** if migration or seeding fails before the revoke, the newly created database and user can remain with broad privileges. The random password is not persisted in `runtime.json` until after success, so the account is unlikely to be usable by an outsider, but the orphaned state and broad grant are avoidable deployment hygiene problems. If failure occurs after seeding but before password rotation, the isolated database contains the known local demo password even though the public runtime is not started.

**Recommendation:** wrap provisioning in cleanup that drops the database/user or revokes privileges on failure; grant least privilege from the start if possible; use separate migration credentials; rotate/seed only in the intended order; report or clean orphaned demo databases/accounts.

## Hardening and deployment requirements

### Remembered-login lifetime remains a policy decision

**Evidence:** `backend/app/Http/Controllers/PortalController.php:14,17-18` passes the optional `remember` flag to `Auth::attempt()` and regenerates the session. `backend/config/session.php:2` sets a 120-minute normal session lifetime but does not override the framework recaller lifetime. The installed framework default is `SessionGuard::$rememberDuration = 576000` minutes (about 400 days) at `backend/vendor/laravel/framework/src/Illuminate/Auth/SessionGuard.php:62`. Logout cycles the remember token and clears the recaller at `SessionGuard.php:666-682`.

**Assessment:** remembered login is opt-in, and logout revokes the persistent credential. A 400-day recaller is nevertheless a long-lived authentication credential and should be an explicit MISO policy decision, especially for administrators. No vulnerability was demonstrated.

**Recommendation:** set an explicit shorter lifetime for privileged accounts, document the policy, and consider requiring re-authentication for especially sensitive actions. Any change to the framework recaller behavior should be covered by session/browser tests.

### Public-host forwarded-protocol dependency

**Evidence:** `scripts/public-router.php:10-17` requires the connector's `X-Forwarded-Proto` value for its HTTPS redirect and replaces `X-Forwarded-For` with the Cloudflare client address. Laravel trusts the loopback connector through `PORTAL_TRUSTED_PROXIES` and reads forwarded protocol headers through `ConfiguredTrustProxies.php:7-11`. The public router itself sets HSTS and the public runtime enables secure/encrypted session cookies.

**Assessment:** the current public-host checks passed with this path, so this is not a current remote exposure. The HTTPS state seen by Laravel still depends on the connector/header configuration; that dependency should be verified end-to-end if MCP is ever enabled on the public host, because `AuthenticateMcp.php:16` requires HTTPS in production. Keep the current disabled default until that deployment path is explicitly tested.

### Configuration cache and public-runtime reuse

**Evidence:** `scripts/init-public-demo.php:29` sets `APP_CONFIG_CACHE` under `.local/public-host`, but no launcher step builds a config cache. If an operator later runs `config:cache` under one runtime and then reuses/updates the runtime, the cached configuration can override the new environment until the cache is cleared.

**Recommendation:** either build and invalidate the cache as part of a documented deployment step or keep it absent. Verify the effective URL, database, session, and MCP settings after any reuse.

### Additional low-priority hardening notes

- `backend/routes/web.php:15` limits `POST /api/requests` by IP (`throttle:30,1`), while other mutations use the account-scoped `portal-mutations` limiter (`:12,16,17,20,21`). This is not an access-control issue; consider adding the account-scoped limiter with a separate IP backstop if shared-NAT behavior matters.
- `backend/tests/Support/TestEnvironmentGuard.php:7-10` accepts MySQL host `localhost` as well as `127.0.0.1`. On platforms where `localhost` selects a Unix socket, the port restriction may not identify the same target. Requiring `127.0.0.1` would make the MySQL test allow-list less ambiguous. The default test path remains SQLite `:memory:`.
- The CSV guard in `backend/app/Services/ReportRenderer.php:41-44` covers whitespace, separators, control/format characters, and non-spacing marks (`\p{Mn}`). It does not cover every Unicode mark class; see the unverified hypothesis below.

## Unresolved policy decisions

- Final remembered-login lifetime and administrator re-authentication rules.
- Whether the public MCP pilot may ever be enabled on the temporary public host, and its proxy/HTTPS configuration.
- Public-demo secret storage and cleanup requirements for the hosting PC.
- Production database privileges, TLS, backup, retention, and host/proxy ownership.
- Whether `POST /api/requests` should be account-scoped for rate limiting.
- Whether the CI browser/frontend suite is required on every branch or only release branches.

## Unverified hypotheses and candidates not established

- **CSV formula injection through Unicode spacing/enclosing marks:** the sanitizer checks `\p{Mn}` but not all mark categories (`\p{Me}`/`\p{Mc}`). A spreadsheet-specific bypass was not demonstrated; XLSX uses explicit string typing and PDF output is Blade-escaped. Broaden the guard and add tests if this threat matters.
- **Open redirect in `frontend/src/App.tsx:333`:** `window.location.assign(loc.pathname + loc.search)` uses a React Router pathname. The component is reached through the `/login` route, and no pathname that becomes an external authority was demonstrated. Replacing it with a same-origin URL construction would remove the residual concern.
- **MCP origin normalization:** `backend/app/Http/Middleware/AuthenticateMcp.php:19` compares the raw `Origin` header with configured strings. A cross-origin browser cannot set that header to a trusted value, and mismatches fail closed. No bypass was established.
- **Framework/session edge cases:** the review did not establish an exploitable login-CSRF, session-fixation, or account-switch path. Login uses JSON + trusted Origin/Referer, session regeneration, and an active-account check.

## Verified controls

| Area | Review result | Primary evidence |
| --- | --- | --- |
| Requester ownership and office scope | Requester queries are owner-scoped; administrators are handling-office-scoped; personnel require current-office assignment (or final completed assignment); unauthorized detail/report access returns 404/403. | `backend/app/Services/RequestAccess.php:9-20`, `:21-35`; `FeedbackReader.php:10-23`, `:60-80`; `ReportJobReader.php:11-17` |
| Workflow integrity | Mutations run in transactions, lock the request row, require `expected_version`, increment `record_version`, enforce configured transitions, require active assignment for work states, require a completion message and outcome, and write events/audit rows atomically. | `backend/app/Services/RequestWorkflow.php:33-105`; `backend/config/workflow.php:4-11` |
| Evaluation integrity | Only the authenticated requester can evaluate a completed request; the evaluation table's request key and the service check prevent duplicates; missing feedback does not undo completion. | `RequestWorkflow.php:106-114`; `PortalController.php:40` |
| CSRF/origin | `api/*` unsafe methods require JSON plus a trusted Origin, or a trusted Referer when Origin is absent; cross-site fetch metadata is rejected; cookies are HttpOnly and SameSite=Lax; no state-changing GET route was found. | `VerifyPortalOrigin.php:11-24`; `bootstrap/app.php:9-14`; `config/session.php:2`; `routes/web.php` |
| SQL injection | Search input is bound and uses escaped `LIKE`; raw fragments use hard-coded column names; no user-controlled raw SQL was found. | `RequestReader.php:12-30`; `FeedbackReader.php:39-55`; `RequestAccess.php:9-20` |
| Output injection | React renders untrusted text by default and no `dangerouslySetInnerHTML`/`innerHTML` pattern was found; Blade uses escaped output; PDF has remote/PHP/JS disabled and a view chroot; XLSX uses explicit string typing; CSV applies prefix protection. | `backend/resources/views/reports/service-evaluation.blade.php`; `ReportRenderer.php:18-28`, `:38-49`, `:50-95`; frontend source scan |
| Report privacy | Report jobs and downloads are owner- and office-scoped; paths are generated internally and exact-checked; files are private; missing files return 404; responses use `private, no-store`. | `ReportController.php:10-48`; `ReportJobReader.php:11-30`; `GenerateReport.php:14-32`; `config/filesystems.php:2` |
| MCP | Disabled by default; bearer-only; tokens are hashed by Sanctum with expiry/revocation; authentication requires an active MISO administrator and current office; tool scopes are relational; tools are read-only; arguments/result content are not stored in audit rows. | `AuthenticateMcp.php:15-24`; `McpAuthorization.php:44-64`; `PortalServer.php:18`; `ReadTool.php:36-62`; `routes/ai.php:10-12` |
| Production guard | Non-local/testing startup rejects debug mode, non-HTTPS app URL, insecure/encrypted session settings, invalid/default keys, unsafe database credentials/targets, invalid trusted origins/proxies, and demo seeding. | `ProductionConfiguration.php:10-25`; `AppServiceProvider.php:8-11` |
| Secrets and dependencies | `.env` and private runtime files are ignored; tracked-path scan found only `backend/.env.example`; no private-key/token patterns were found; Composer/npm audits returned no known advisories/vulnerabilities at review time. | `.gitignore:1-22`; tracked-file scan; audit commands below |

## Checks actually run

| Check | Result |
| --- | --- |
| `php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml` | Passed: `OK (77 tests, 570 assertions)` using in-memory SQLite and isolated test storage. |
| `composer audit --working-dir=backend --locked --format=json --no-interaction` | No advisories and no abandoned packages reported. |
| `npm --prefix frontend audit --package-lock-only --json` | 0 reported vulnerabilities across 166 dependencies. |
| `npm --prefix frontend run build` | Passed (`tsc -b && vite build`). The build modified tracked `frontend/tsconfig.tsbuildinfo`; it was restored with `git restore -- frontend/tsconfig.tsbuildinfo`. |
| `python scripts/check-docs.py` | Passed: 74 Markdown files and 398 local links/anchors checked. Re-run after this report was added. |
| `php backend/artisan about --only=environment,drivers` | Laravel 12.70.0, PHP 8.5.12, environment `local`, debug OFF, URL `127.0.0.1:5173`, timezone `Asia/Manila`, MySQL, single logging. |
| `php backend/artisan route:list --path=api -v` | `api/*` routes include the `web` middleware; MCP routes use `throttle:120,1`, `AuthenticateMcp`, and `throttle:portal-mcp`, without the browser session group. |
| Tracked-file secret scan | Only `backend/.env.example` matched the sensitive-path pattern; no private-key, AWS, GitHub, or Slack token patterns were found. Frontend bundle matches for “password” were UI labels, not credentials. |
| `git status --porcelain` | Clean after restoring the build-generated timestamp file. |

## Coverage limits

- GitHub Actions was not executed or observed; the CI finding is based on the workflow source and the repository layout.
- MySQL-specific tests were not run; only the isolated SQLite suite was executed. MySQL concurrency/constraint behavior remains unverified here.
- No Playwright/browser suite, public-endpoint attack, load test, or distributed concurrency test was performed.
- No full Git-history secret scan was performed; tracked working-tree paths and selected patterns were checked.
- No production hosting, proxy/TLS path, firewall, backup, or database-privilege review was performed.
- Dependency audit results are advisory-feed results at review time, not a guarantee that no undisclosed vulnerability exists.
- No real MISO policy decision was invented for remembered-login lifetime, priority/deadline rules, or public-demo operations.

## Remediation order

1. Fix CI triggers, working directory, PHP version, test setup, and add dependency-audit/frontend jobs (F-01).
2. Make MCP enablement strict and fail closed, including a production guard (F-02).
3. Restrict public-demo secret-file permissions and add provisioning cleanup/least privilege (F-03, F-05).
4. Add stale MCP-invocation reconciliation (F-04).
5. Decide remembered-login lifetime and validate public-host forwarding/cache behavior before any MCP pilot on that host.
6. Apply the optional hardening notes (rate limiter consistency, test-host allow-list, Unicode mark coverage, same-origin URL construction) as part of normal maintenance.

## Scope statement

This review was limited to the current repository state and the checks listed above. It does not certify production readiness, real-user data handling, campus policy compliance, MySQL concurrency safety, or the security of the temporary public-demo hosting environment.
