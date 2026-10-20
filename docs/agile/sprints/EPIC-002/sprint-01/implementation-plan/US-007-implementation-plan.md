# US-007 — Service report summaries

Record type: Retrospective implementation plan for EPIC-002 / Sprint 01 (Iteration 1), documented 21 October 2026. Implemented work is described below; this was not an advance sprint agreement.

## User outcome

As a MISO administrator, I can select a calendar month or rolling window and review scoped service activity, ratings and elapsed resolution averages.

## Accepted decisions

The cohort uses submission dates in Asia/Manila; current status/group values reflect generation time. Ratings exclude independent general feedback. Resolution uses valid calendar elapsed hours. Repeated subjects use case/whitespace normalization rather than AI.

## Implementation

- [ServiceEvaluationReport.php](../../../../../../backend/app/Services/ServiceEvaluationReport.php)
- [ReportController.php](../../../../../../backend/app/Http/Controllers/ReportController.php)
- [reports.tsx](../../../../../../frontend/src/reports.tsx)

## Acceptance checks

- Month boundaries, future submissions and handling-office scope produce the expected cohort.
- Category, priority, status and personnel group totals agree with the cohort count.
- Verified-rating and valid-resolution denominators exclude missing/invalid values; empty averages are null/em dashes.
- Recurring subjects and final/current assignee attribution follow the disclosed definitions.

## Evidence and boundaries

See the [story verification](../sprint-01-tests-US-007.md) and [canonical Iteration 1 log](../../../../../changes/iteration-01/sprint-01/001-reporting-and-feedback.md).

Business-hour response targets, SLA compliance, historical month-end state and semantic/AI clustering are excluded.
