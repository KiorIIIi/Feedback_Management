# Security Audit — 20 October 2026

## Scope and status

Read-only security audit of the local CLSU Feedback Management System prototype at `D:\Projects_26\Feedback_Management`.

This review covered authentication and sessions, authorization and office scope, CSRF/origin/CORS controls, injection and output handling, privacy of internal notes and evaluations, workflow transitions, stale-version handling, report generation, dependency exposure, and test-environment isolation.

This was not a production penetration test. No production HTTP endpoint was exercised, no live deployment configuration was available, and no load, spreadsheet-execution, or full Git-history secret scan was performed.

**Result:** no confirmed critical- or high-severity unauthorized data-access or privilege-escalation flaw was found in the current local prototype. The highest-priority issues concern deployment hardening and account-availability behavior.

## Findings

| # | Severity | Confidence | Finding |
| --- | --- | --- | --- |
| 1 | Medium (High if deployed unchanged) | High | Development defaults in `.env.example` and session configuration are not guarded by a fail-closed production check. |
| 2 | Medium | High | Account-scoped login throttling enables targeted lockout of a known account. |
| 3 | Low | High | CORS defaults permit wildcard origins for `/api/*`; current writes are separately origin-checked. |
| 4 | Low–Medium | Medium | No trusted-proxy configuration exists, which can weaken client-IP throttling and HTTPS-dependent headers behind a TLS-terminating proxy. |
| 5 | Low | High | Log level is hardcoded to `debug`. |
| 6 | Low | Medium | CSV formula sanitization misses some invisible Unicode prefixes. |
| 7 | Low | High | CSP is applied to Laravel responses but not to a production static SPA. |
| 8 | Low | High | “Remember me” is checked by default and creates a long-lived recaller cookie. |
| 9 | Informational | High | A synthetic test export is tracked in Git. |
| 10 | Informational | Medium | Notifications are dispatched inside the workflow transaction. |
| 11 | Informational | High | Read endpoints have no dedicated throttling. |
| 12 | Informational | High | Demo credentials are documented and seeded only in local/testing contexts. |

### 1. Development defaults can reach production without failing closed

**Locations**

- `backend/.env.example:2,5,10-13`
- `backend/config/session.php:2-3`

**Condition**

The example environment uses local-development values: `APP_ENV=local`, an HTTP `APP_URL`, local database credentials, and `SESSION_SECURE_COOKIE=false`. There is no startup validation that rejects these values when the application is deployed outside local development.

**Impact**

An unchanged deployment could run with an insecure session cookie, an unintended database account, or local-only assumptions. The practical severity depends entirely on the deployment environment; the repository currently contains only the local prototype.

**Fix**

- Add a production configuration check that fails closed when `APP_ENV` is not local/testing and any of the following are unsafe: debug mode, HTTP application URL, insecure session cookie, missing encryption, weak/default database credentials, or missing trusted origins.
- Keep the example file explicitly marked as development-only.
- Verify the check with configuration probes rather than relying on documentation.

**Regression tests**

- Boot with production-like values and assert valid secure settings pass.
- Boot with each unsafe value and assert startup fails.
- Confirm local/testing defaults remain usable for development.

### 2. Targeted login lockout

**Locations**

- `backend/app/Providers/AppServiceProvider.php:7-10`
- `backend/config/security.php:4-5`
- `backend/routes/web.php:7`

**Condition**

Login attempts are limited by IP and normalized, hashed email. The account-scoped bucket means an attacker who knows or guesses an account identifier can consume that account’s attempts and cause a temporary denial of service for the legitimate user.

**Impact**

Account availability. This is not an authentication bypass or privilege escalation.

**Fix**

- Combine account and network signals instead of a single hard account lock.
- Use progressive delays or short cooldowns, with monitoring and user notification.
- Consider a challenge after repeated failures rather than a fixed lockout.
- Preserve the existing hashed-email key so raw addresses are not stored in limiter keys.

**Regression tests**

- Confirm a failed attempt for one account does not lock unrelated accounts.
- Confirm a legitimate user can recover after the cooldown.
- Confirm limiter keys do not contain plaintext email addresses.

### 3. Wildcard CORS for API paths

**Locations**

- No application `backend/config/cors.php`
- Framework default: `backend/vendor/laravel/framework/config/cors.php:18,22,26,32`

**Condition**

The framework default allows `*` origins for `api/*` with wildcard methods and headers, while `supports_credentials` is false. The application’s browser writes are additionally protected by `VerifyPortalOrigin`, including an explicitly trusted Origin or trusted Referer when Origin is absent.

**Impact**

The configuration is broader than necessary. No cross-origin unauthorized data access was established: credentials are unsupported, and write paths are origin-checked.

**Fix**

- Publish `backend/config/cors.php`.
- Allowlist the actual frontend origins and required methods/headers.
- Keep credentials disabled unless a specific cross-origin credential flow is designed and reviewed.

**Regression tests**

- Assert allowed origins receive CORS headers.
- Assert an unlisted origin does not.
- Recheck that cookie-authenticated writes still require a trusted Origin/Referer.

### 4. Missing trusted-proxy configuration

**Locations**

- `backend/bootstrap/app.php:7-14`
- No `backend/config/trustedproxy.php`

**Condition**

Laravel does not trust forwarded headers from a reverse proxy. Behind a TLS-terminating proxy, the application may see HTTP and the proxy’s IP instead of the client’s IP.

**Impact**

- Per-IP login and write throttling can collapse onto the proxy address.
- HSTS emission (`backend/app/Http/Middleware/PortalSecurityHeaders.php:14`) may not activate because the request does not appear secure.
- Client-IP-dependent audit and rate-limit data can be misleading.

**Fix**

- Configure trusted proxies explicitly for the deployment topology.
- Do not trust arbitrary forwarded headers.
- Verify HTTPS detection, HSTS, and client-IP throttling through the actual proxy path.

**Regression tests**

- Configure a trusted proxy and assert the forwarded client IP is used.
- Assert an untrusted proxy header is ignored.
- Assert HSTS is emitted only for secure requests.

### 5. Debug logging hardcoded

**Location**

- `backend/config/logging.php:2`

**Condition**

The default log level is fixed to `debug`.

**Impact**

Verbose application logs may retain more operational detail than necessary in a production deployment.

**Fix**

- Drive the level from environment configuration.
- Default production to `warning` or stricter.
- Confirm logs do not contain credentials, tokens, cookies, or full request bodies.

**Regression tests**

- Configuration probe for production default.
- Explicit override probe.
- Log-payload review for sensitive fields.

### 6. CSV sanitizer misses invisible Unicode prefixes

**Locations**

- `backend/app/Jobs/GenerateReport.php:13-15`

**Condition**

A local probe found that the sanitizer recognized plain and non-breaking-space prefixes but did not recognize a BOM, zero-width space, or right-to-left override prefix. Spreadsheet interpretation depends on the client and the exact content.

**Impact**

Possible formula injection when an exported cell begins with an invisible character followed by a dangerous prefix. Exploitability was not confirmed with Excel or another spreadsheet application.

**Fix**

- Normalize or inspect Unicode code points before checking the first significant character.
- Apply the apostrophe guard when the first meaningful character is dangerous.
- Consider a maintained CSV-escaping library and spreadsheet-specific tests.
- Preserve human-readable exported text.

**Regression tests**

- Cover BOM, zero-width space, right-to-left override, whitespace, newline, tab, and ordinary prefixes.
- Assert safe text remains unchanged.
- Verify generated files remain valid CSV.

### 7. CSP does not cover the production static SPA

**Locations**

- `backend/app/Http/Middleware/PortalSecurityHeaders.php:12-14`
- `frontend/vite.config.ts:6-9`

**Condition**

Laravel responses receive security headers, including CSP in production. A production deployment that serves the built SPA as static files must apply equivalent headers at the web server or CDN. The current Vite development configuration is not a production header policy.

**Impact**

The SPA HTML could be served without the intended CSP, reducing defense in depth against script injection.

**Fix**

- Define the production header policy in the actual web server/CDN configuration.
- Keep the policy compatible with the built application and any required asset origins.
- Document that this is a deployment responsibility, not something the local prototype certifies.

**Regression tests**

- Production-like header probe for the SPA HTML.
- CSP validation against the built asset graph.
- Confirm no unsafe inline script requirement is introduced.

### 8. “Remember me” enabled by default

**Locations**

- `frontend/src/App.tsx:433`
- Session/recaller configuration under `backend/config/session.php`

**Condition**

The login form checks “Remember me” by default, creating a long-lived recaller cookie unless the user unchecks it.

**Impact**

On a shared device, a user may unintentionally retain a persistent login. This is a usability/privacy risk rather than an authentication bypass.

**Fix**

- Default the checkbox to off.
- Consider a shorter recaller lifetime.
- Ensure logout invalidates the recaller and server-side session state.

**Regression tests**

- Assert the checkbox is unchecked by default.
- Assert opt-in creates the expected persistent cookie.
- Assert logout removes both session and recaller state.

### 9. Synthetic test export tracked in Git

**Location**

- `backend/storage/framework/testing/disks/local/reports/1.csv`

**Condition**

A synthetically generated test export is present in Git history. It is not production data, but test artifacts should not be versioned.

**Fix**

- Remove the file from tracking.
- Add the testing storage path to `.gitignore`.
- Keep test storage isolated and disposable.
- Confirm no real requester data was ever written to that path.

### 10. Notifications dispatched inside the transaction

**Locations**

- `backend/app/Services/RequestWorkflow.php:26,93-95`

**Condition**

Workflow notifications are dispatched during the transaction that changes request state.

**Impact**

A notification worker could observe state that is not yet committed, or a later rollback could leave a notification that no longer matches the stored record. This is a reliability and consistency concern, not a confirmed privacy breach.

**Fix**

- Dispatch notifications after commit.
- Prefer an outbox or after-commit queue dispatch.
- Preserve the existing requester/internal visibility filtering.

**Regression tests**

- Roll back a transition and assert no notification is sent.
- Commit a transition and assert exactly the intended recipients are notified.
- Confirm internal notes never generate requester-visible notifications.

### 11. No dedicated read-endpoint throttling

**Locations**

- `backend/routes/web.php:10-18`

**Condition**

Authenticated read endpoints do not have a dedicated throttle comparable to the mutation and login limits.

**Impact**

An authenticated account could scrape or repeatedly poll authorized data, or consume application resources. No unauthorized cross-scope access was found.

**Fix**

- Add a conservative account-scoped read limit where operationally appropriate.
- Avoid disrupting ordinary dashboard polling.
- Monitor repeated enumeration patterns.

### 12. Demo credentials are local/testing-only

**Condition**

Demo accounts are documented and seeded for local or testing use. The seeding path is guarded, and no production credentials were found in source control during this review.

**Fix**

- Keep demo seeding explicitly environment-gated.
- Ensure production deployment does not run demo seeders.
- Avoid documenting reusable credentials outside local setup material.

## Verified protections

The audit confirmed the following controls in the current implementation:

- Disabled accounts are terminated from active sessions by `EnsureActiveAccount`.
- Login regenerates the session; logout invalidates session state.
- Request access is scoped by role, office, ownership, and assignment in `RequestAccess`.
- Final assignees receive the access intended by the workflow; unrelated personnel do not.
- Browser writes require a trusted Origin/Referer through `VerifyPortalOrigin`.
- SQL values are bound; no SQL injection path was established.
- React output is escaped; no stored/reflected XSS path was established.
- Model fillable lists exclude sensitive role, status, ownership, job-number, priority, and version fields.
- Internal notes and requester-visible events are filtered by visibility.
- Personnel do not receive private evaluation remarks.
- Workflow changes use row locks, stale-version checks, and ULID job numbers under concurrency controls.
- Reports are stored privately and downloads are authorization-checked.
- Security headers are applied to Laravel responses.
- MCP is disabled in the current prototype.

## Checks performed

| Check | Result |
| --- | --- |
| Backend suite on SQLite | `OK (39 tests, 183 assertions)` |
| Backend suite on MySQL test DB `clsu_feedback_test` (`127.0.0.1:3307`) | `OK (39 tests, 183 assertions)` |
| `composer audit --working-dir=backend --locked --format=json --no-interaction` | No advisories; no abandoned packages |
| `npm --prefix frontend audit --package-lock-only --json` | Zero reported vulnerabilities |
| CSV prefix probe | Plain and NBSP prefixes sanitized; BOM, zero-width space, and RTL override not recognized |
| Playwright/browser suite | Not rerun in this pass; existing documentation reports 31 passing tests |
| Live production HTTP probe | Not performed |
| Spreadsheet execution test | Not performed |
| Load/concurrency stress test | Not performed |
| Full Git-history secret scan | Not performed |

## Remediation order

1. Add fail-closed production configuration validation and secure session/cookie defaults.
2. Configure trusted proxies and allowlisted CORS for the actual deployment.
3. Apply the production CSP/HSTS policy to the static SPA at the web server or CDN.
4. Reduce targeted login-lockout impact.
5. Normalize Unicode in CSV sanitization and add spreadsheet-safe regression tests.
6. Disable “Remember me” by default and review recaller lifetime.
7. Dispatch notifications after commit.
8. Remove the tracked synthetic test export and add read-endpoint throttling where appropriate.

## Boundaries and unresolved assumptions

- The production `.env` was intentionally not inspected; no secrets were printed.
- No live production or reverse-proxy environment was available.
- The CSV Unicode gap is confirmed at the sanitizer level, but spreadsheet exploitability was not tested.
- The possible `window.location.assign` path concatenation at `frontend/src/App.tsx:331` was not established as a reachable open redirect.
- This report does not certify production readiness or the absence of undisclosed vulnerabilities.
