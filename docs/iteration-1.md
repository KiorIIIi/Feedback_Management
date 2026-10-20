# Iteration 1 — Reporting and feedback review

Implementation period: 20–21 October 2026 (Asia/Manila).
Status: Implemented and verified within prototype scope; results are recorded below.
System: CLSU Feedback Management System and Service Tracker.

This records the user's requested additions and changes for this iteration. It is an implementation log, not MISO approval, production acceptance, or a claim that all proposed architecture features exist.

## Requested changes

1. Implement the research paper's automated report generation feature.
2. Add a separate category assigned by staff, rather than treating service types as request categories.
3. Let administrators open and read an entire submitted feedback message.
4. Record the additions, changes, verification and remaining limitations as Iteration 1.

## Additions and changes

| Area | Previous behavior | Iteration 1 behavior |
| --- | --- | --- |
| Reporting periods | Rolling day windows | Rolling windows and a selected calendar month, using Asia/Manila |
| Report summaries | Dashboard totals, priority chart and CSV register | Breakdowns by category, official priority, status and personnel; verified-rating averages; elapsed resolution averages; recurring subjects |
| Export formats | CSV request register | PDF report, native Excel workbook (`.xlsx`) and retained CSV register |
| Category classification | Service type only; separate request category unavailable | Administrator-defined office categories, assigned or cleared during open-request review |
| Category integrity | No category records | Additive category migration, office-local unique names, composite category/handling-office foreign key, version checks and transactional history/audit |
| General feedback reading | Inline messages; no dedicated reading screen or supplied contact detail view | Explicit **View full feedback** link and private reading page showing the complete message, rating, category, date, optional contact details and unverified job reference |
| Verified evaluation reading | Inline remarks and a service link | Explicit **View full evaluation** page with complete remarks and a link to the authorized service record |
| Feedback history | Latest 100 general submissions and evaluations only | Separate pagination for each list, 25 records per page, so older submissions remain reachable |
| Feedback navigation and recovery | No detail route | Refreshable detail routes, return link, loading/error states and retry controls |
| Public demonstration | Device-hosted synthetic demo established during this work | Updated frontend and report queue on the same temporary HTTPS tunnel; source and credentials stay outside the served folder |

The device-hosting work also added startup/stop scripts, an isolated public demo database and runtime user, random public-demo account passwords, a private credential file, and a hosted frontend build. The obsolete top demo-access banner was removed at the user's request. The original local demo remains separate. This is a temporary device-hosted demonstration, not GitHub Pages hosting.

## How to use the additions

As a MISO administrator, open **Reports & analytics**. Select a period or **Calendar month**, review the summaries, choose PDF/Excel/CSV and press **Export report**. Download becomes available after the queue succeeds. Expand **How these figures are calculated** for definitions.

Create an office category at the bottom of that page. Open an unfinished service request, choose **Request category** in **Manage request**, and save the update. Existing records remain Uncategorized; completed records remain immutable.

Open **Feedback & ratings**, then choose **View full feedback** or **View full evaluation** under an entry. The reading page preserves the complete message and line breaks. Supplied general-feedback contact details are visible only within the authorized private view. An entered job number remains unverified text and cannot open a private request. Previous/Next controls appear when a list has more than one page.

## Calculation and access decisions

- Reports select requests by submission date. Group values and status reflect generation time, rather than historical month-end state.
- Average rating uses available verified evaluations on completed requests in the submission cohort. Missing evaluations and independent general-feedback ratings are excluded. The rating count is shown.
- Average resolution uses valid completion-minus-submission calendar hours divided by the eligible completed count. Missing, negative and future completion timestamps are excluded. Eligible and excluded counts are disclosed. This is not a business-hour or SLA calculation.
- Personnel attribution counts each request once: current active assignee for open work, final assignee for completed work.
- Recurring concerns use repeated subjects after case and whitespace normalization, with at least two occurrences. This is exact-text grouping; semantic analysis and AI classification are not implemented.
- Categories remain independent of service types. The user chose staff assignment; no MISO category taxonomy is invented or seeded. Administrator creation is serialized by office and audited; request changes preserve expected-version and transactional history checks.
- Reports remain private and queued. Requesting administrator identity, active role and office are checked again for job status and downloads. Failed/missing files expose no download link. Excel treats submitted strings as text; CSV formula prefixes are neutralized.
- Independent general feedback remains routed to MISO. Other-office administrators cannot read it. Authenticated requesters can read only their own submissions; guest submissions grant no requester access. Verified evaluation access follows request ownership or administrative handling-office scope. Personnel cannot read feedback or evaluations.

## Implementation map

| Component | Main files |
| --- | --- |
| Shared report calculation | `backend/app/Services/ServiceEvaluationReport.php` |
| Private PDF/Excel/CSV rendering | `backend/app/Services/ReportRenderer.php`, `backend/resources/views/reports/service-evaluation.blade.php` |
| Queued report lifecycle | `backend/app/Jobs/GenerateReport.php`, `backend/app/Http/Controllers/ReportController.php` |
| Category persistence and workflow | `backend/database/migrations/2026_10_20_000200_add_request_categories.php`, `backend/app/Services/RequestCategories.php`, `backend/app/Services/RequestWorkflow.php` |
| Feedback access and pagination | `backend/app/Services/FeedbackReader.php`, `backend/app/Http/Controllers/PortalController.php`, `backend/routes/web.php` |
| Report and feedback screens | `frontend/src/reports.tsx`, `frontend/src/pages.tsx`, `frontend/src/App.tsx` and associated styles |
| Regression coverage | `backend/tests/Feature/ReportCategoriesTest.php`, `ServiceEvaluationReportTest.php`, `FeedbackReadingTest.php`; `frontend/tests/reports.spec.ts`, `feedback-reading.spec.ts` |
| Public artifact verification | `frontend/scripts/check-report-exports.mjs`; evidence under ignored `.local/public-host/reports-qa/` |

PDF rendering uses pinned Dompdf 3.1.6; native Excel rendering uses PhpSpreadsheet 5.10.0. Dependency versions are recorded in Composer manifests and the lockfile. React/TypeScript/Vite, Laravel and centralized MySQL remain the application stack.

## Verification record

Before the feedback-reading addition, the complete backend suite passed on SQLite and isolated MySQL: **53 tests, 385 assertions on each**. Those runs cover report arithmetic and date bounds, category ownership/office constraints, invalid category rollback, stale versions, real PDF/Excel/CSV generation, text/formula handling, private downloads, changed-office denial and job outcomes.

The report/category browser flow subsequently passed, including category creation/assignment, calendar month selection, queued PDF/Excel downloads and the empty state. Four responsive browser tests also passed across the existing role workspaces. Eleven earlier workflow/UI regression tests passed; an initial new report test exposed a missing category label association, which was fixed before the successful rerun. Browser inspection also found stylesheet layer order affecting the new toolbar; stylesheet loading order was corrected.

Public checks downloaded actual PDF, Excel and CSV files, checked breakdown totals against the displayed summary, rejected anonymous downloads and captured desktop/mobile report pages. The initial five-page PDF was rendered and inspected; its personnel heading could be separated from its table, so the template was adjusted to keep each short breakdown block together. The final five-page PDF was rendered and visually inspected on every page; its headings, tables, footers and complete request register were readable.

An attempted browser rerun was temporarily rejected because automatic approval review reached a usage limit. The requested action did not execute during that rejection. After work resumed, the same approved browser-test command ran successfully; no approval bypass was used.

### Final results · 21 October 2026

| Check | Result actually obtained |
| --- | --- |
| TypeScript + hosted Vite build | Passed; current build published to the device-hosted demo |
| Complete SQLite backend suite | **57 tests, 417 assertions passed**, including the new feedback access/pagination tests |
| MySQL backend verification | Complete 53-test suite passed before the feedback addition (385 assertions); targeted final feedback and CSV checks passed afterward (**5 tests, 58 assertions**) on `clsu_feedback_test` only |
| Browser checks across staged runs | **20 distinct tests passed**: three workflow/export/asset tests, eight UI regression tests, one category/monthly report test, four responsive tests, three role tests and one complete-feedback reading test |
| Public HTTPS checks | All eight checks passed: monthly/four-breakdown total agreement; queued PDF/Excel/CSV completion with anonymous download rejection; desktop/mobile report fit; complete feedback reading/privacy; verified evaluation/service navigation |
| PDF artifact | Final five-page A4 landscape report rendered and visually inspected; final layout saved privately as `report-layout.pdf` |
| Final export regression after PDF layout adjustment | Passed: 1 test, 40 assertions |
| Downloaded Excel artifact | Seven expected worksheets; summary totals and register row count matched the report service; no executable formula cells |
| UI inspection | Desktop/mobile report and feedback pages inspected; no page-level horizontal overflow or browser JavaScript errors in public checks; wide tables retain internal horizontal scrolling |
| Documentation | Relative local links checked; iteration log linked from README and implementation notes |

Private evidence is in `.local/public-host/reports-qa/verification.json`, report/feedback screenshots, downloaded artifacts and final PDF page renders. It is ignored by Git because exports and feedback screenshots are private. No passwords or runtime secrets are included in this log.

The date rollover exposed an existing CSV-test fixture issue: a seeded submission at 09:20 was in the future when the test ran shortly after midnight. The CSV security test now explicitly gives its subject request an eligible past submission time; the application continues excluding future submissions. The initial full-feedback browser test also led to explicit text elements for supplied contact fields, and its rerun passed.

## Remaining boundaries

Scheduled report delivery, outbound email, semantic/AI concern analysis, business-hour/SLA calculations, category rename/delete, completed-record recategorization and MCP execution are outside this iteration. General-feedback reply/moderation workflows are not added. The full proposed schema has not been certified as 3NF, and no MISO acceptance, exhaustive accessibility/security audit, load test or production-readiness claim is made.

The public link depends on this PC, its internet connection and the temporary tunnel. Credentials and private exports must not be committed. See [implementation notes](implementation-notes.md), [verification history](verification.md), and the [README](../README.md) for operational details.
