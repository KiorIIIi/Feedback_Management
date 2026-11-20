# Iteration 03 / Sprint 04 — Shareable event feedback forms

User-designated grouping: **20 November 2026, Asia/Manila**. Requested feature: multiple independently shareable feedback forms for different events at the same time, each with its own link, QR code and optional time/date limits or expiration. The user also approved publishing the accumulated work to `main` after this sprint's records were complete.

| Record | Scope |
| --- | --- |
| [001 — Event feedback forms with expiry](001-event-feedback-forms.md) | Named event forms (`feedback_forms`) with unguessable public tokens, optional open/close window and manual close; public `/feedback/f/{token}` gate; admin share panel upgrade with form management; regression and browser tests |

Sprint status: implemented and verified against the isolated test databases. See the record for commands and results. Expiration uses calendar Manila time; no business-hour rules are implied. Publishing the front-main integration to `main` follows this record at the user's instruction.

[Iteration overview](../iteration-overview.md) · [All iterations](../../README.md)
