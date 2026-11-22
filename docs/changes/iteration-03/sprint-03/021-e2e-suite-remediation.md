# Browser-suite remediation for stale specs and the assistant key-list budget

Iteration 03 / Sprint 03. Follow-up to the 20 November 2026 [full-system audit](../../../testing/audits/system-audit-2026-11-20.md) findings A2, A3 and A12: the Playwright suite was red at HEAD before the shared feedback-form work began, because two specs were never updated for the recorded 16–19 November UI changes, one spec clicked an off-canvas sign-out button, and the assistant key listing consumed the write-limiter budget that ordinary admin use of the unified assistant page already needs.

## Identity

- Iteration: 03
- Sprint: 03
- Change number and title: 021 — Browser-suite remediation for stale specs and the assistant key-list budget
- Date: 20 November 2026, Asia/Manila
- State: verified
- Request or reason: The full browser suite failed 5 of 45 tests with none of the day's feature changes applied; the failures contradicted recorded behavior.

## Changes

- `frontend/tests/roles.spec.ts`: the admin branch asserted the pre-[017](017-shared-staff-login.md) role-specific rejection alert, and the requester branch asserted the removed "Switch account to continue" switch flow. Both now assert the recorded shared sign-in behavior: a personnel account at a legacy role URL signs in and is routed to its own workspace (then signs out to continue the admin flow), and an existing session on a legacy role URL redirects to the signed-in account's workspace. Server authorization is untouched — the backend still restricts when a `role` is sent, which the API-driven tests continue to cover.
- `frontend/tests/assistant-setup.spec.ts`: removed the click on a "Connection settings" navigation tab that the unified assistant page ([010](010-assistant-sidebar.md)/[014](014-dashboard-navigation.md)) removed, and replaced the stale `.connection` / "MCP ready" assertion with the current `.mcp-settings-status` / "MCP server: Enabled" status. The OAuth client registration and revocation steps in that spec now actually run again.
- `frontend/tests/portal.spec.ts`: the visual-evidence test clicked "Sign out" while still in the mobile viewport, but since sprint [014](014-dashboard-navigation.md) the admin sign-out lives in the sidebar, which is off-canvas below desktop widths. The test now returns to a desktop viewport before signing out.
- `backend/routes/web.php` + `backend/tests/Feature/AssistantSetupTest.php`: `GET /api/assistant/keys` moved out of the 6/min `assistant-admin` write limiter (audit finding A12). It remains behind `auth`, the controller's MISO-administrator check, and the safe-method `portal-reads` budget — the same treatment as `GET /assistant/settings`. Measured cause: the unified assistant page refetches the key list on every visit and after each settings save, so one visit plus one save consumed ~5 of the 6 write budget and the next assistant request within the minute returned 429 (browser tests and ordinary admin use alike). New regression test: ten rapid key-list reads stay available.

No other application behavior changed in this record. A separate feature-followup correction (rendering "Collection source · shared link" only when a label exists) is part of the [020](020-shareable-feedback-form-link.md) change after `feedback-revamp.spec.ts` caught the extra "Not supplied" row.

## Verification

| Check | Actual result |
| --- | --- |
| Reproduction at clean HEAD (`git stash` of all working-tree changes, isolated app) | The assistant spec and the portal visual-evidence test failed identically without any of today's changes |
| Instrumented browser probe (temporary spec, removed after) | `x-ratelimit-remaining: 1` after one assistant page visit plus one settings save, confirming the A12 budget starvation |
| Assistant + portal specs after the fixes | PASS: 6/6 (assistant 3, portal 3) |
| roles.spec with updated assertions | PASS: 3/3 |
| feedback-revamp.spec with the conditional collection-source row | PASS |
| Backend assistant suites after the route change | PASS: 8 tests, 95 assertions (includes the new key-list regression test) |

## Remaining work

- The full-suite re-run result after these edits is recorded with the [020](020-shareable-feedback-form-link.md) verification evidence; any remaining reds would be new findings, not the pre-existing ones documented here.
- Audit findings A1 and A4–A11 remain open for disposition per the audit report.
