# Backend error audit remediation — 16 November 2026

Iteration 03 / Sprint 03. User authorized correcting the findings in the [backend error audit](../../../testing/audits/backend-error-audit-2026-11-16.md) and recording the work here. The audit report remains the findings evidence; this record describes what changed, what was verified, and what remains deferred.

## Finding dispositions

| Finding | Resulting behavior |
| --- | --- |
| M01 — N+1 list rendering | `RequestAccess::presentMany()` batches assignments, offices, service types, categories and evaluations per page (fixed query count instead of ~7 per row), and `RequestReader`/`RequesterDashboardService` use it. Detail rendering and per-record authorization are unchanged; lists were already fetched through the scoped query. `RequestReader::search()` still returns a Collection, preserving its MCP callers. |
| M02 — Clarification dead end | **Deferred to product policy.** No transition was invented. The limitation is now documented in `config/workflow.php` beside the transition map: only the requester can leave *For Clarification*, and no cancelled/withdrawn state exists. An administrative escape requires an explicit MISO decision. |
| M03 — Queue retry window | `GenerateReport` declares `$timeout = 600` and `$backoff = [60, 300]`; the database queue's `retry_after` is raised from 120 s to 900 s, so a worker cannot re-claim a job while a render is still running. Retries keep the existing idempotent status writes. |
| M04 — Sync queue accepted in production | `ProductionConfiguration::assertSafe()` now rejects `queue.default === 'sync'` outside local/testing, and the provider passes the queue configuration to the guard. `backend/.env.example` states the production override. |
| M05 — Heavy synchronous summary | Added `ServiceEvaluationReport::summary()`, an aggregate variant for the dashboard GET that selects only the columns needed for totals, groupings and recurring concerns and never builds export rows. `ReportController::summary` calls it; export generation (`build()`) is unchanged. Shared `period()`/`concerns()` helpers keep both paths consistent. |
| M06 — CORS missing verbs | `allowed_methods` now includes `PUT` and `DELETE`, matching the registered settings/revocation routes. Origin normalization and CSRF-adjacent protections are unchanged. |
| L01 — Office-null category 404 | Category creation aborts with 403 when the administrator has no handling office, before the locked office lookup. |
| L02 — Pre-transaction membership check | The requesting-office membership check moved inside the create transaction. |
| L03 — Raw data spread into inserts | `RequestWorkflow::evaluate()` and `GeneralFeedback::submit()` whitelist their columns explicitly; caller-supplied keys can no longer become column names. |
| L04 — Unfiltered metrics ratings | `ReportingService::metrics()` applies the same `whereBetween('rating', [1,5])` filter as the evaluation report. |
| L05 — Mislabelled MCP failures | The local synthetic check defaults unknown failures to `temporary_failure`; only HTTP 403/404/422 map to client error codes. |
| L06 — Catch-all permission_denied | `ReadTool` maps 404 → `not_found`, 429 → `rate_limited`, 5xx → `temporary_failure`, other HTTP errors → `permission_denied`. |
| L07 — Hour-long demo credential | The local synthetic check shortens its issued token to a five-minute expiry immediately after creation, in addition to the existing revoke in `finally`. |
| L08 — Dead validation branch | Removed the unreachable in-transaction enable gate in `AssistantSettings::save()`; the existing hard rejection with the local-only policy message remains authoritative. |
| L09 — Inconsistent origin comparison | `AuthenticateMcp` normalizes scheme/host/port the same way as `VerifyPortalOrigin` and CORS before comparing against trusted origins. Unknown origins still fail closed. |
| L10 — Duplicate requester event | A status change carrying a requester-visible message now records one event; internal-visibility messages still record both the status event and the separate internal note. |
| L11 — Downloads on the generic limiter | New `portal-downloads` limiter (30/min per user, `security.download_per_minute`) applied to `reports/{id}/download`. |
| L12 — Insecure example defaults | `.env.example` documents the required production overrides next to each local default; the runtime production guard is unchanged. |

No schema migration, dependency change, workflow-policy change, provider enablement, or operational MCP write was introduced. M02 remains the only finding without a code change, by design.

## Tests

New `backend/tests/Feature/BackendAuditRemediationTest.php` covers: single requester event for a status+message update (L10), out-of-range ratings excluded from metrics (L04), report summary without request rows (M05), list presentation fields after batching (M01), office-less category creation returning 403 (L01), CORS exposing PUT/DELETE (M06), queue retry window exceeding the job timeout with a non-empty backoff (M03), and the production guard rejecting a sync queue (M04). Existing lifecycle, MCP, notification, reporting and security suites continue to pass unchanged.

## Verification

| Check | Actual result |
| --- | --- |
| PHP syntax (`php -l`) on all changed PHP files | PASS |
| Full backend PHPUnit suite (in-memory SQLite, PHP 8.5.12) | PASS: 109 tests, 815 assertions, 1 intentional skip (gated MySQL provisioning integration test; MySQL server offline locally). |
| Targeted remediation tests | PASS: 8 tests, 27 assertions. |
| MCP suites after the batch-read refactor | PASS: 24 tests, 209 assertions. |
| Documentation links/anchors (`python scripts/check-docs.py`) | PASS (see repository index run for this change). |

The suite was run before and after adding the new tests; the pre-existing 101-test baseline stayed green throughout. Failures encountered while applying the refactor were limited to `RequestReader::search()` returning an array to MCP callers, corrected by wrapping the batch result in `collect()`.

## Review boundaries

- Query-count reduction (M01) and inline-summary cost (M05) are covered by behavioral tests and code structure, not by load measurements; no profiling claim is made.
- M03's timeout values are configuration choices for this prototype, not MISO-approved SLAs.
- M02 stays open pending a product decision on administrative closure or cancellation of abandoned clarifications.
