# System audit remediation

Iteration 03 / Sprint 03. User authorized implementing the open findings from the 20 November 2026 [full-system audit](../../../testing/audits/system-audit-2026-11-20.md) after reviewing the reported improvements. This record dispositions every finding; the audit report remains the findings evidence.

## Identity

- Iteration: 03
- Sprint: 03
- Change number and title: 022 — System audit remediation
- Date: 20 November 2026, Asia/Manila
- State: verified
- Request or reason: "can u implement these improvements" — fix the published audit findings.

## Finding dispositions

| Finding | Resulting behavior |
| --- | --- |
| A1 — Malformed login crash + throttle bypass | The `portal-login` account key uses an `is_string` guard; array/missing emails hash to one shared `malformed` bucket instead of raising inside the throttle middleware. Malformed logins now return 422 through validation and are eventually throttled with 429; no 500s. |
| A4 — Late report failure overwrites success | `GenerateReport::failed()` no longer downgrades a `completed` job; a processing/pending job still marks failure. Regression-tested both directions. The remaining overlap (a re-claimed attempt re-rendering a completed export) stays idempotent — same rows, same file — and the queue's `retry_after` (900 s) already exceeds the job timeout. |
| A5 — Six dependency advisories | **Resolved.** Composer was not broken — the shell's `/c/...` path form is not valid for native Windows programs; `php C:/composer/composer.phar` works. Updated within existing constraints: laravel/framework **v12.73.0**, phpseclib **4.0.4**, symfony/http-foundation **v7.4.23**, symfony/http-kernel **v7.4.23**, symfony/mime **v7.4.22**, symfony/routing **v7.4.23**. `composer audit` now reports no advisories. |
| A6 — Stale save resets another request's editor | `RequestDetail.save()` captures the record id at submit and compares it against the currently displayed id before applying success state, clearing the edit flag or reloading; errors are likewise only shown on the originating record. The PATCH itself is unchanged. |
| A7 — Live list shrinkage strands an empty page | The request list clamps its page state when a response reports a smaller `last_page`, and the Next button disables on `page >= last_page`. Out-of-range pages no longer render the misleading empty state. |
| A8 — Dead switch-account code and stale docs | The unreachable `expectedRole` branch and its switch-account UI are removed from `App.tsx`; existing sessions on any login entry simply redirect to their own workspace (the recorded 017 behavior). The README and architecture login-routing sections now describe that behavior instead of the removed flow. |
| A9 — Export polling ignores visibility/offline | Export status polling is gated on `document.visibilityState`/`navigator.onLine` with wake events and bounded failure backoff (1.5 s → 30 s), matching the live workspace's gating. A stuck pending export can now be dismissed from the notice. |
| A10 — Launcher trusts another project's worker | `start.ps1` also matches this project's artisan path in the saved worker's command line before reusing its PID. |
| A13 — Whole-cohort aggregate reads | `ReportingService::metrics()` no longer materializes request rows: totals, awaiting, completed, priority buckets, daily buckets and rating count/average are SQL aggregates through the same access scope. A parity regression test compares the SQL results with the previous row-based reference for admin, personnel (the complex assignment scope) and requester. The dashboard `summary()` keeps its row pipeline (per-row assignment semantics, grouped summaries and recurring concerns need row data) but now selects only the evaluation and assignment columns it uses; a full SQL rewrite of that path remains deferred until measured. |
| A14 — Feedback audit entries lack record attribution | `GeneralFeedback::submit()` records `after: {general_feedback_id}` in the audit entry, inside the existing transaction. Anonymous submissions remain attributable to their record. |
| A15 — migrate-public trusts validated metadata only | The CLI now rejects a cached configuration before bootstrap (the same guard the public provisioning script applies) and verifies the effective connection host/port/database against the validated runtime before installing operator credentials. Verified by syntax check and code review; the public runtime is not initialized locally, so no end-to-end run was possible. |
| A11 — Draft loss on session expiry | **Deferred.** Needs a small product decision (what is worth persisting and for how long, and only for the same account), not an unambiguous defect fix. |

No authorization, privacy, workflow-transition or guest-policy behavior changed. The rate-limit reclassification (021/A12) and these fixes keep every scope check in place.

## Verification

| Check | Actual result |
| --- | --- |
| New `backend/tests/Feature/SystemAuditRemediationTest.php` | PASS: 4 tests, 37 assertions — malformed-login throttling, failure-cannot-downgrade-completed, audit record attribution, and metrics parity for all three roles |
| Full backend PHPUnit suite after all fixes | PASS: 118 tests, 880 assertions, 1 intentional skip |
| Full backend PHPUnit suite after the dependency updates (Laravel 12.73) | PASS: 118 tests, 880 assertions, 1 intentional skip |
| `composer audit` after the updates | No security vulnerability advisories found |
| Frontend build (`tsc -b && vite build`) | PASS |
| Full Playwright suite | PASS: 45/45 (7.0 min) on the remediated code with the upgraded dependencies |
| PHP syntax on changed files (`php -l`) | PASS |

## Remaining work

- A11 draft preservation awaits a product decision.
- A13's full SQL rewrite of the dashboard `summary()` path is deferred pending measurement; its column footprint is already reduced.
- The dependency update needs the same `php backend/artisan migrate`-free deploy path as any vendor update on the public demo: rebuild the frontend artifact for hosted use.
