# Event feedback forms with expiry

Iteration 03 / Sprint 04. User request, 20 November 2026: "we should be able to make new feedback forms for different events simultaneously and add time and date limit to the form generated or add expiration in general". This builds on the [020 shareable link feature](../sprint-03/020-shareable-feedback-form-link.md) — instead of one general form with an optional label, administrators can now run any number of named event forms at the same time, each with its own unguessable link, QR code and acceptance window.

## Identity

- Iteration: 03
- Sprint: 04
- Change number and title: 001 — Event feedback forms with expiry
- Date: 20 November 2026, Asia/Manila
- State: verified
- Request or reason: Simultaneous per-event forms with time/date limits or expiration, recorded in a new sprint and published to `main` at the user's instruction.

## Changes

- **Schema** (migration `2026_11_20_000700_create_feedback_forms.php`): new `feedback_forms` table — unguessable 32-hex `token` (unique), `title` (≤200), optional `description` (≤1000), nullable `opens_at`/`closes_at` (Manila calendar time), `is_active` flag, `created_by` administrator. `general_feedback.feedback_form_id` nullable foreign key links responses to their form; the earlier `source_label` quick-label flow is unchanged.
- **Public link**: `GET /api/feedback-forms/{token}` (unauthenticated, throttled) returns only that form's public fields — id, title, description, window and status. The frontend route `/feedback/f/:token` shows the form with the event title and "Accepting responses until …" while open, and a clear explanation instead of the form when scheduled, expired, or closed. Unknown tokens are 404.
- **Acceptance window enforcement (server-side)**: `GeneralFeedback::submit()` refuses submissions for forms that are inactive, not yet open, or past their close time (422 with a specific message), and records `feedback_form_id` on the response and in the audit entry. Window consistency (close after open) is enforced on create and update against the merged record. Anonymous submission, MISO-only review scope, and the unverified-job-number rule are unchanged.
- **Admin management** (`FeedbackForms` service; routes behind auth + admin check, mutation throttles): create, list (with status and response counts), and update — manual **Close/Reopen**, plus title/description/window edits. Every change writes an audit entry.
- **Share panel upgrade** (`share-feedback.tsx`): two modes — **Event form** (default) with a live-updating list of forms showing status, window, response count and Close/Reopen actions, plus an inline create form with `datetime-local` open/close inputs; and the previous **Quick label link** mode. Selecting or creating a form generates its `/feedback/f/{token}` link with the existing copy, QR PNG/SVG download and print poster (the poster shows the event title). Invalid window input is reported before submission.
- **Reading view**: the admin feedback detail shows "Collection source · shared link" with the event form's title for form responses, the quick label for labelled links, and nothing for direct submissions.

No service-request workflow, evaluation, guest-moderation or authorization rule changed. The public metadata endpoint exposes no administrative fields (no token, no active flag).

## Verification

| Check | Actual result |
| --- | --- |
| New `backend/tests/Feature/FeedbackFormsTest.php` | PASS: 4 tests, 42 assertions — admin create/list/update with audit entries and window validation, scoped public metadata (unknown token 404, no admin fields), two simultaneous forms accepting and attributing responses, and refusals before open, after close and after manual close |
| Full backend PHPUnit suite (in-memory SQLite, PHP 8.5.12) | PASS: 122 tests, 922 assertions, 1 intentional skip — includes the A14 audit-attribution test updated to tolerate the additive `feedback_form_id` field |
| Frontend build (`tsc -b && vite build`) | PASS |
| `frontend/tests/share-form.spec.ts` (isolated Playwright app) | PASS: 3/3 — the label-link test now switches to Quick label link mode; the new test covers create → share link/QR → public submission within the window → manual close → "no longer accepting responses" at the same link → admin attribution |
| Full Playwright suite | PASS: 46/46 |
| Migration applied to the running local demo database | `2026_11_20_000700_create_feedback_forms` applied (additive, no reseed) |
| Documentation links (`python scripts/check-docs.py`) | PASS |

## Remaining work

- Expiration is calendar Manila time; no business-hour or timezone-per-campus handling (consistent with the existing report definitions).
- The form list shows live status via the shared polling budget; a per-form response export or aggregate view is future work.
- The quick-label mode keeps the general `/feedback` form permanent by design; deprecating it in favor of event forms only would be a separate product decision.
