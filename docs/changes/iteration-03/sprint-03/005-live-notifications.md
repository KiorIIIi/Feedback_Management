# Live notifications and automatic updates — 24 October 2026

Iteration 03 / Sprint 03. User request: show received feedback, requests and notifications without manually refreshing the website.

## Change

Added transactional staff alerts for new requests, independent feedback and verified evaluations. Inbox reads/acknowledgements enforce current record/office access and return typed links plus an accurate unread total beyond the latest 100 notices. A shared feed updates badges for every role and displays one dismissible alert for newly received notices.

Extended the existing data-loading hook with opt-in five-second background checks, hidden/offline pauses, non-overlapping reads, bounded retries/timeouts and cancellation. Lists, dashboards, personnel workload, feedback and report summaries refresh without loading flashes or filter resets. Request detail refresh pauses during editing/saving; stale saves retain drafts until the user explicitly loads current state.

The additive notification migration adds a kind, feedback foreign key and inbox index. No historical alerts or original-record rewrites are performed. [Automatic workspace updates](../../../project/live-updates.md) is the canonical behavior/access/rollout guide. No AI/MCP provider, WebSocket dependency, external delivery or public deployment is added.

## Verification

| Check | Observed result |
| --- | --- |
| Full backend suite on in-memory SQLite | PASS: 93 tests, 694 assertions, one intentionally disabled public-demo provisioning integration test. |
| Full backend suite on local MySQL 8.0.30 | PASS: 93 tests, 694 assertions, the same optional provisioning skip. Target was only the allowlisted `clsu_feedback_test` on loopback port 3307. |
| New notification feature tests | PASS: six tests / 43 assertions, included in the full runs. Covers scoped recipients, typed feedback/evaluation targets, private-data minimization, reauthorization on read/acknowledgement, repeated acknowledgement, transaction rollback, unread totals beyond 100 and foreign keys. |
| Full isolated Chrome suite | PASS: all 39 tests in one final run. New cases verify separate-session receipt without a document reload, live filtered lists/badges, read-state synchronization, preservation of staff drafts and stale versions, requester status/privacy, retry recovery, hidden/offline pauses and non-overlapping reads. |
| Frontend production build | PASS: TypeScript and Vite. Generated tracked TypeScript metadata restored rather than included. |
| Desktop/mobile visual check | PASS: notification screenshots at 1440px and 390px inspected; mobile overflow and reachable dismissal checked. Test capture waits for the existing navigation transition to finish. |
| PHP syntax | PASS for the new services and migration. |
| Documentation | PASS: 78 Markdown files, 434 local links/anchors; Git whitespace check passed. |

Initial browser checks corrected test fixtures: request search is entered through its existing control rather than assuming URL-search support, and the requester status assertion targets the summary badge rather than an ambiguous matching history label. The final complete run passed without retries.

## Local installation

Started the existing project-owned MySQL instance after confirming the documented local target. Applied the pending additive MCP foundation migration and the new notification extension to `clsu_feedback` on loopback port 3307. Before/after aggregate counts matched: 12 requests, 0 general-feedback entries and 12 notifications. No seed, reset, credential issuance or public deployment was performed. The backend test runs used a separate allowlisted test database and fake private storage.

The [first GitHub run](https://github.com/KiorIIIi/Feedback_Management/actions/runs/40069376822) passed the MySQL, SQLite, frontend, documentation and Windows jobs. All three new live-update browser cases passed; the complete browser job finished with 38 passed and one existing responsive-navigation check failing after its final full-page reload. The viewport test now returns through the actual Dashboard navigation and explicitly waits for its destination, preserving the mobile-menu assertions while avoiding another unrelated session-restoration cycle. No production read limit or application authorization control was relaxed. Final remote results will be recorded after this test adjustment.

The adjusted responsive suite passed all four local checks (public surfaces plus requester, personnel and administrator workspace routes).

The [second CI run](https://github.com/KiorIIIi/Feedback_Management/actions/runs/40070072729) again passed all new live cases and all non-browser jobs; the browser failure moved to the following administrator reload. The high-volume UI runner now uses an explicit testing-only read budget of 1200, matching its existing testing-only login budget approach. Ordinary local/production/staging retain 240 reads/minute, and testing without the override also defaults to 240. A subprocess regression verifies those environment boundaries; the existing account-isolation/throttling feature test remains active. Application scopes, mutation limits and session rules are unchanged.

[Sprint overview](sprint-overview.md) · [Testing index](../../../testing/README.md)
