# Shareable feedback form link with event QR

Iteration 03 / Sprint 03. User request, 20 November 2026: analyze the system and implement a Google-Forms-style way to generate a feedback form link with a QR code for events. This record covers the feature; the accompanying full-system audit findings are recorded separately in [the 20 November audit](../../../testing/audits/system-audit-2026-11-20.md).

## Identity

- Iteration: 03
- Sprint: 03
- Change number and title: 020 — Shareable feedback form link with event QR
- Date: 20 November 2026, Asia/Manila
- State: verified
- Request or reason: Generate a public feedback form link and QR code (Google Forms style) usable for events and printed material; record where responses came from.

## Changes

The public `/feedback` page and the unauthenticated `POST /api/feedback` endpoint already existed, so no new public entry point or guest policy was introduced — the feature shares the existing anonymous general-feedback flow.

- **Share panel (admin)**: `frontend/src/share-feedback.tsx` adds a "Share feedback form" panel on Feedback & ratings (`/app/feedback`, admin only). It builds `<address>/feedback[?event=<label>]` from an editable base (prefilled with the current origin so the hosted public-demo URL can be pasted), shows the link with a copy button, renders a QR code, and offers PNG (512 px) and SVG downloads plus a print-ready event poster. The base address and label are remembered in `localStorage`; an invalid address is reported and disables the actions instead of producing a broken link.
- **QR codes** are generated entirely client-side by `qrcode.react` 4.2.0 (React 19 compatible, no external service), error correction level M with a two-module margin.
- **Event poster printing**: `@media print` rules in `frontend/src/feedback.css` scope printing to the poster, but only while the poster is rendered (`body:has(.feedback-share-poster)`), so printing any other page is unaffected. Browsers without `:has()` fall back to normal printing.
- **Collection label**: an optional event/collection label (max 120 characters) is appended to the link as `?event=...`. The public form shows a note that the form was shared for that label and submits it as `source_label`. New nullable `general_feedback.source_label` column (migration `2026_11_20_000600_add_feedback_source_label.php`); `POST /api/feedback` validates `nullable|string|max:120` and `GeneralFeedback` stores it. The admin feedback detail view shows "Collection source · shared link" when present. The label is untrusted text: it grants no access and changes no workflow.
- **Privacy boundaries unchanged**: general feedback stays separate from verified service evaluations; an entered job number still grants nothing; the label never appears in evaluation payloads; office/MISO scoping of feedback reads is untouched.

## Verification

| Check | Actual result |
| --- | --- |
| `php -l` on all changed PHP files | PASS |
| New `backend/tests/Feature/FeedbackSourceTest.php` | PASS: 4 tests, 18 assertions (label stored for anonymous and authenticated submissions, null without a label, 120/121-character validation, owner-only visibility) |
| Full backend PHPUnit suite (in-memory SQLite, PHP 8.5.12) | PASS: 114 tests, 843 assertions, 1 intentional skip (MySQL-gated provisioning test), including the same-day key-list regression test from change 021 |
| Frontend build (`tsc -b && vite build`) | PASS |
| New `frontend/tests/share-form.spec.ts` (isolated Playwright app) | PASS: 2 tests — panel link/QR/clipboard/PNG download, and full anonymous labeled submission visible to the admin detail view |
| Full Playwright suite | PASS: 45/45 (5.5 min) after the change-021 remediation; the suite had been red at HEAD before today's work |

One full-suite backend run failed once at `McpFoundationTest::test_reports_require_owner_office_success_and_an_existing_private_file` (download URL null after storage write) while four audit subagents, the full Playwright suite and two builds were running on the same machine. The same test passed in isolation, paired with the new test class, and in a re-run of the full suite on a quiet machine; the new test class was moved out and back during triage. Treated as a load-related flake, recorded as a test-stability observation in the audit.

## Remaining work

- The base address is manually entered; the hosted demo tunnel URL still changes on restart, so links printed for an event stop resolving once that tunnel is recycled. A stable hostname is a deployment decision, not a prototype one.
- Label reporting is per-response only; there is no aggregate "responses per event" view yet.
- Poster branding uses the existing local Figma assets; a MISO-approved layout would be needed for real print material.
