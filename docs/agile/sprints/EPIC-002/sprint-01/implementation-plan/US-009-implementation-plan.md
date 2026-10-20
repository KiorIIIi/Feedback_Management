# US-009 — Private queued report exports

Record type: Retrospective implementation plan for EPIC-002 / Sprint 01 (Iteration 1), documented 21 October 2026. Implemented work is described below; this was not an advance sprint agreement.

## User outcome

As a MISO administrator, I can request a PDF, native Excel workbook or CSV register, follow the queue result and download only my authorized completed export.

## Accepted decisions

The same report snapshot/calculations feed the screen and renderer. Files are private. Job status and downloads recheck requester, active administrator role and handling office. CSV retains the register; PDF/Excel include summaries and definitions.

## Implementation

- [GenerateReport.php](../../../../../../backend/app/Jobs/GenerateReport.php)
- [ReportRenderer.php](../../../../../../backend/app/Services/ReportRenderer.php)
- [ReportController.php](../../../../../../backend/app/Http/Controllers/ReportController.php)
- [service-evaluation.blade.php](../../../../../../backend/resources/views/reports/service-evaluation.blade.php)
- [check-report-exports.mjs](../../../../../../frontend/scripts/check-report-exports.mjs)

## Acceptance checks

- Pending, processing, failed and missing-file states do not claim a downloadable completed export.
- Only successfully stored files complete the job; completed-job retries reuse the result.
- Changed-office/unauthorized access is rejected and export text cannot become spreadsheet formulas.
- PDF and XLSX signatures, workbook sheets, totals and register contents match the report data.

## Evidence and boundaries

See the [story verification](../sprint-01-tests-US-009.md) and [canonical Iteration 1 log](../../../../../changes/iteration-01/sprint-01/001-reporting-and-feedback.md).

Scheduled email/delivery, load testing, PDF accessibility certification and production retention are excluded.
