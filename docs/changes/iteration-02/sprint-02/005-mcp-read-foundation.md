# MCP foundation — authenticated read-only pilot

23 October 2026, Asia/Manila. Iteration 02 / Sprint 02, appended to the existing cycle at the user's request to begin the planned MCP implementation.

## Delivered scope

Implemented the first read-only slice of US-012: official Laravel MCP 1.1.1 and Sanctum 4.3.3, opt-in stateless `/api/mcp`, five scoped read tools, trusted operator issuance/revocation commands, relational tool scopes and durable invocation provenance. Exact dependencies are locked; the existing Laravel 12.70.0 version was retained. The [canonical implementation guide](../../../architecture/mcp-implementation.md) records setup, tool inputs, schema consistency, authentication and remaining boundaries.

Request search, personnel workload and report-job reads now use shared services across API and MCP. Search handles `%`/`_` as literal characters. Report status verifies the expected private-file path before advertising a download. No operational request or reporting writes are available through MCP. The embedded assistant and provider remain disconnected, and no live/public deployment or pilot credential was created by this work.

## Verification evidence

| Check | Result |
| --- | --- |
| Full backend suite, SQLite `:memory:` | PASS — 77 tests, 570 assertions; includes 17 MCP foundation tests and existing lifecycle/security/report regressions. |
| Full backend suite, MySQL 8.0.30 `clsu_feedback_test` on loopback port 3307 | PASS — 77 tests, 570 assertions, including actual MySQL scope foreign-key and uniqueness enforcement. |
| `npm --prefix frontend run build` | PASS — TypeScript project build and Vite production build. No frontend source changes were required. |
| `python scripts/check-docs.py` | PASS — 64 Markdown files and 332 local links/anchors; final record checked before commit. |
| `git diff --check` | PASS. |
| `php C:/composer/composer.phar validate --working-dir=backend --no-check-publish` | Valid manifest/lock; warnings for the existing unspecified license and intentionally exact dependency constraints. |
| `php backend/artisan route:list --path=api/mcp` | PASS — official POST transport plus GET/DELETE unsupported-method routes. |
| Composer installation/audit | Two new packages installed; zero dependency upgrades; no known vulnerability advisories reported at installation. |

Backend command: `php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml`. The MySQL run sets `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3307` and `DB_DATABASE=clsu_feedback_test` for that command process. The test bootstrap's existing guard rejects other MySQL targets. Report files use faked private storage; credentials/records are synthetic.

The initial MySQL run could not connect because the project-owned instance was stopped and was interrupted after repeated connection failures. A focused diagnostic confirmed connection refusal before migrations. The existing startup script was then used to start the isolated instance and ensure the allowlisted test database exists. No development/public-demo records were migrated or reseeded.

The new tests cover legacy initialization and current-protocol discovery/header matching, per-tool catalog/authorization, mandatory bearer authentication, expired/revoked credentials, role/deactivation/office changes, owner/token consistency, revoked scopes, audit outcomes, minimal fields, paginated history, literal search, metrics/workload, report ownership/availability, API isolation, HTTPS/origin checks, rate limits and scope foreign-key/uniqueness constraints. The initial test helper name conflicted with PHPUnit's final `result()` method and was renamed; an HTTPS test was corrected to send an actual HTTPS URL. These test-only failures were corrected before the successful full SQLite run.

No external model/provider or independently running MCP client was connected. Browser end-to-end tests, hosted CI and live deployment checks were not run for this backend foundation. Write retries, stale operational versions and approval consumption are outside this read-only milestone and are not claimed as verified MCP capabilities.

## Remaining boundaries

Operation-id retries, write provenance, atomic operational outcomes and applicable bound approvals must precede MCP writes. Attention ranking needs an established policy. External multi-user OAuth, actual external-client interoperability and provider/runtime integration remain deferred. This milestone neither selects campus policy nor establishes production readiness.

[Sprint overview](sprint-overview.md) · [Implementation guide](../../../architecture/mcp-implementation.md)
