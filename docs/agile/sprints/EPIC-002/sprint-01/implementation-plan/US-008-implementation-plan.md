# US-008 — Separate staff-assigned categories

Record type: Retrospective implementation plan for EPIC-002 / Sprint 01 (Iteration 1), documented 21 October 2026. Implemented work is described below; this was not an advance sprint agreement.

## User outcome

As a MISO administrator, I can define categories for my handling office and assign an independent category during review of an open request.

## Accepted decisions

The user explicitly chose a separate staff-assigned category. Categories do not replace service types. Existing requests stay Uncategorized; completed requests remain immutable. No category taxonomy is invented.

## Implementation

- [2026_10_20_000200_add_request_categories.php](../../../../../../backend/database/migrations/2026_10_20_000200_add_request_categories.php)
- [RequestCategories.php](../../../../../../backend/app/Services/RequestCategories.php)
- [RequestWorkflow.php](../../../../../../backend/app/Services/RequestWorkflow.php)
- [pages.tsx](../../../../../../frontend/src/pages.tsx)

## Acceptance checks

- Only administrators create office-local categories; normalized duplicate names are rejected.
- The composite foreign key prevents category/handling-office mismatch on MySQL.
- Review checks the expected request version and commits request, internal event and audit together.
- Foreign-office, requester/personnel, stale-version and invalid-transition changes fail without partial writes.

## Evidence and boundaries

See the [story verification](../sprint-01-tests-US-008.md) and [canonical Iteration 1 log](../../../../../changes/iteration-01/sprint-01/001-reporting-and-feedback.md).

Category rename/delete, bulk backfill and recategorizing completed records are excluded.
