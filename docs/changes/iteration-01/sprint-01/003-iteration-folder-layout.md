# 003 — Iteration folder layout

Iteration: 01. Sprint: 01. Date: 21 October 2026.  
Scope: Documentation-only organization requested by the user.

## Request

Give each iteration its own folder so multiple changes in one sprint can be added without replacing the iteration's existing records.

## Changes

- Created an iteration overview and a separate sprint change index.
- Moved the original reporting/feedback record to `001-reporting-and-feedback.md`.
- Moved the documentation-organization record to `002-documentation-reorganization.md`.
- Added this follow-up as record 003 in the same sprint.
- Added a reusable change-record template and documented the next-number rule.
- Updated incoming/outgoing links and repository guidance to use iteration/sprint folders.

The [sprint index](sprint-overview.md) is the list of change records. The [iteration overview](../iteration-overview.md) lists its sprints. Agile story plans and tests retain their existing home and link to these change records.

## Verification and limits

Local documentation links and Markdown heading anchors are checked with `python scripts/check-docs.py`; whitespace is checked with `git diff --check`. This change does not modify application code, database records or hosting, and does not introduce a new iteration cycle or a production acceptance claim.

Results: 48 Markdown files and 248 local links/anchors passed; `git diff --check` passed. No application tests were rerun for this documentation-only change.
