# Sprint 01 tests — US-007: Service report summaries

State: Recorded checks passed within the cited prototype scope. This file reorganizes prior evidence; it does not claim a fresh test run on the documentation-reorganization date.

## Relevant commands

Run from the repository root with the configured PHP 8.5 executable. The following are scoped entry points; the actual recorded evidence below includes full and staged suite runs.

```powershell
php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml --filter "ServiceEvaluationReportTest"
npm --prefix frontend run test:e2e -- reports.spec.ts
```

## Recorded evidence

Report arithmetic, boundaries, empty results and scope were covered in the 53-test full backend runs on SQLite/MySQL. The final SQLite suite passed 57 tests / 417 assertions. The category/monthly browser flow and public monthly/breakdown total checks passed. These are suite/flow results, not a standalone story assertion count.

## Acceptance coverage

- Month boundaries, future submissions and handling-office scope produce the expected cohort.
- Category, priority, status and personnel group totals agree with the cohort count.
- Verified-rating and valid-resolution denominators exclude missing/invalid values; empty averages are null/em dashes.
- Recurring subjects and final/current assignee attribution follow the disclosed definitions.

## Sources and limits

[Iteration 1 results](../../../../changes/iteration-01/sprint-01/001-reporting-and-feedback.md#verification-record) · [Verification history](../../../../testing/verification.md) · [Implementation plan](implementation-plan/US-007-implementation-plan.md)

Business-hour response targets, SLA compliance, historical month-end state and semantic/AI clustering are excluded.
