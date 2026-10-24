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

GitHub Actions results for the published feature commit will be recorded separately from these local checks.

[Sprint overview](sprint-overview.md) · [Testing index](../../../testing/README.md)
