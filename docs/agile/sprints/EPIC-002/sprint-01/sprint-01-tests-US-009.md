# Sprint 01 tests — US-009: Private queued report exports

State: Recorded checks passed within the cited prototype scope. This file reorganizes prior evidence; it does not claim a fresh test run on the documentation-reorganization date.

## Relevant commands

Run from the repository root with the configured PHP 8.5 executable. The following are scoped entry points; the actual recorded evidence below includes full and staged suite runs.

```powershell
php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml --filter "ServiceEvaluationReportTest|test_csv_neutralizes_whitespace_prefixed_formula_content"
npm --prefix frontend run test:e2e -- reports.spec.ts portal.spec.ts
```

## Recorded evidence

Real renderer/private-download tests passed within the recorded full backend suites. The final export regression after the PDF pagination adjustment passed 1 test / 40 assertions. Queued PDF, XLSX and CSV public downloads passed anonymous-access checks. All five PDF pages were rendered/inspected; downloaded Excel had seven expected sheets, matching totals and no formula cells.

## Acceptance coverage

- Pending, processing, failed and missing-file states do not claim a downloadable completed export.
- Only successfully stored files complete the job; completed-job retries reuse the result.
- Changed-office/unauthorized access is rejected and export text cannot become spreadsheet formulas.
- PDF and XLSX signatures, workbook sheets, totals and register contents match the report data.

## Sources and limits

[Iteration 1 results](../../../../changes/iteration-01/sprint-01/001-reporting-and-feedback.md#verification-record) · [Verification history](../../../../testing/verification.md) · [Implementation plan](implementation-plan/US-009-implementation-plan.md)

Scheduled email/delivery, load testing, PDF accessibility certification and production retention are excluded.
