# Sprint 01 tests — US-008: Separate staff-assigned categories

State: Recorded checks passed within the cited prototype scope. This file reorganizes prior evidence; it does not claim a fresh test run on the documentation-reorganization date.

## Relevant commands

Run from the repository root with the configured PHP 8.5 executable. The following are scoped entry points; the actual recorded evidence below includes full and staged suite runs.

```powershell
php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml --filter "ReportCategoriesTest"
npm --prefix frontend run test:e2e -- reports.spec.ts
```

## Recorded evidence

Category constraints, authorization, stale versions and rollback were covered in the complete 53-test SQLite/MySQL runs. The browser category creation/assignment flow passed after its missing label association was fixed. The additive migration was applied without reseeding populated demos.

## Acceptance coverage

- Only administrators create office-local categories; normalized duplicate names are rejected.
- The composite foreign key prevents category/handling-office mismatch on MySQL.
- Review checks the expected request version and commits request, internal event and audit together.
- Foreign-office, requester/personnel, stale-version and invalid-transition changes fail without partial writes.

## Sources and limits

[Iteration 1 results](../../../../changes/iteration-01/sprint-01/001-reporting-and-feedback.md#verification-record) · [Verification history](../../../../testing/verification.md) · [Implementation plan](implementation-plan/US-008-implementation-plan.md)

Category rename/delete, bulk backfill and recategorizing completed records are excluded.
