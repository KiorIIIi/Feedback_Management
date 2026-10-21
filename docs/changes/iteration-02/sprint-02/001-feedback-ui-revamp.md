# 001 — Feedback UI revamp

Date: 21 October 2026, Asia/Manila.

Iteration: 02 / Sprint: 02. Story: US-013 under EPIC-002.

Status: Implemented locally; verification evidence below. No deployment or production-readiness claim.

## Authorization and design source

The user authorized application implementation of the planned feedback revamp and explicitly selected **Option A — list and separate detail pages**. They requested this work be documented as Iteration 02 / Sprint 02.

Visual authority is the existing Figma file's **UI Feedback V2** page:

- [List](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-181)
- [General feedback details](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-182)
- [Verified evaluation details](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-183)
- [Mobile reading](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-185)

High-fidelity Figma context and screenshots were inspected before implementation. The existing role shell, navigation, university assets and self-hosted font remain in use. Screen designs were translated to native React/CSS with real records and controls, rather than copying Figma's synthetic content into the application.

## Decisions carried into implementation

| Decision | Delivered behavior |
| --- | --- |
| Option A | Tabbed list opens a dedicated message/detail route; Option B's desktop inbox is not implemented. |
| Smaller headings | 26/34 px desktop titles; 20/28 px mobile titles; 18/26 px section headings; 22/30 px displayed ratings. |
| Readable content | 16/26 px messages, 14/21 px metadata, desktop message measure capped at 600 px; full messages preserve line breaks. |
| Compact source pills | 12 px green outlined pills for Independent feedback / Verified evaluation, at the card's top right beside the rating. |
| CLSU identity | Existing green/gold palette and Plus Jakarta Sans; darker supporting text within the feedback surface. |
| Separate feedback types | General feedback opens initially; tabs show separate authorized totals. Numeric ratings have supporting stars; absent ratings say Not rated. |
| General-feedback facts | Contact fields are labeled as supplied; missing values say Not supplied. Typed references remain unverified text, with no private-service link. |
| Verified context | Related service includes job number, service type, requesting office, final assigned personnel, completion date and staff-assigned request category. |
| Mobile reading | Single-column reading, smaller headings, wrapping references and a jump-to-context action. |

The dark-green backgrounds requested in Figma organize the **canvas sections behind the screen frames**. They are not an application dark-theme requirement. The application retains the reviewed light screen surfaces.

Feedback categories remain Complaint / Suggestion / Compliment / Other. The feedback-category filter is separate from staff-assigned service-request categories; this sprint does not introduce another taxonomy or category-assignment workflow.

## Added features and before/after behavior

| Before | After |
| --- | --- |
| Two long stacked lists, evaluations first | General-feedback and evaluation tabs, general initially active |
| Small previews/metadata and large rating tiles | Aligned rows with 16 px previews, 14 px metadata, compact ratings and explicit opening links |
| Pagination-only list API | Real server-side substring search, rating, date and feedback-category filters |
| Detail title duplicated in a large container | Subject as the page title; rating/source header, full message and context in a desktop two-column layout |
| Evaluation detail only linked to the request | Authorized service context is displayed before opening the service request |
| Return link discarded list state | Query parameters retain tab, filters and independent pagination; per-account/session scroll offsets restore the browsing position |
| Missing values and errors had limited context | Explicit Not rated / Not supplied states, no-results recovery and retry without losing filters |

General-list previews normalize whitespace and show at most two lines. Complete content remains available on the detail route. Missing evaluation remarks display No additional remarks. Subjects, messages, names and references remain escaped React text.

## Implementation and interface behavior

[feedback.tsx](../../../../frontend/src/feedback.tsx) and [feedback.css](../../../../frontend/src/feedback.css) own the scoped UI. Shared loading logic moved unchanged from the page module into [useLoad.ts](../../../../frontend/src/useLoad.ts), retaining request cancellation and stale-response handling. The original page module re-exports the feedback routes.

The existing `GET /api/feedback` response remains compatible with both lists and their pagination. It now also returns `counts.general` and `counts.evaluations`, calculated inside the caller's existing scope before applying filters.

| Parameter | Meaning |
| --- | --- |
| `search` | Up to 200 characters; literal substring in subject/message/unverified reference for general feedback, or subject/remarks/job number for evaluations. Percent and underscore are searched literally. |
| `rating` | `low` (1–2), `unrated`, or a specific `1`–`5` |
| `days` | `7`, `30`, or `90`; calendar days in Asia/Manila, including today, from the first day's midnight through the current instant |
| `category` | General-feedback category only; not a service-request category |
| `general_page`, `evaluation_page` | Independent positive page numbers; 25 records per page |

Search/rating/date choices are shared while switching tabs. Category remains in the URL but is hidden/ignored for evaluations. Filters apply on submit; applying filters resets both page numbers. Clearing filters retains the active tab. Tab totals describe all authorized records, while the result footer describes the filtered page. Date-filtered results exclude future timestamps.

The `tab` query parameter controls the interface; it cannot grant access. Detail links carry the browsing query, so refresh and explicit Back retain it. Session storage contains only a pixel offset keyed by account and query, never feedback payloads or credentials. If storage is disabled, URL state still works and scroll restoration falls back to the top.

[FeedbackReader](../../../../backend/app/Services/FeedbackReader.php) supplies shared scoped reads; controller validation stays thin. Filtering precedes pagination and uses deterministic timestamp/ID ordering.

Verified detail adds left-joined service/office/category names and a scalar final-assignment lookup. Final assignment uses the greatest assignment ID, matching existing RequestAccess/reporting semantics; historical assignment rows do not duplicate results. Missing office/personnel/category values show No office affiliation / Unassigned / Uncategorized. A category join also requires the request's handling office.

No migrations, new dependencies, service writes, authentication changes, report definitions or workflow transitions were introduced.

## Authorization and privacy preserved

General feedback stays MISO-only for administrators and owner-only for authenticated requesters. Guest submissions are not visible to unrelated requesters. Verified evaluations remain restricted by requester ownership or administrative handling office. Personnel remain denied. Counts, searches, filters and detail-context queries use these same restrictions; search does not broaden scope.

General references do not verify a request, create a service link or grant evaluation rights. Supplied contact facts are not inferred from the submitter's account. Existing service-request authorization also applies after opening a related service.

No reply, moderation, read/unread tracking, sentiment analysis, MCP integration or operational workflow changes were added.

## Asset provenance

Existing shell assets stay local. Seven exact SVG exports from the reviewed Figma frames are stored in `frontend/public/assets/figma/feedback-review/`:

| Files | Source / slot | Root geometry |
| --- | --- | --- |
| `search.svg` | List frame 102:181, search field | 20 × 20 |
| `back.svg` | General detail frame 102:182, return link | 18 × 18 |
| `rating-1.svg` through `rating-5.svg` | List frame 102:181, corresponding synthetic rating's star group | 86 × 14 |

Star exports are selected dynamically from the real numeric rating. Runtime code contains no temporary Figma asset URLs. Browser checks inspect non-empty loaded images and their effective dimensions.

## Verification evidence

| Check | Result |
| --- | --- |
| TypeScript + Vite production build | Passed on the final implementation |
| Complete backend suite, guarded SQLite memory DB | **60 tests, 446 assertions passed** |
| Targeted feedback suite, guarded SQLite memory DB | **7 tests, 61 assertions passed** |
| Same feedback suite, MySQL 8 on allowlisted `clsu_feedback_test` / port 3307 | **7 tests, 61 assertions passed** |
| Chrome feedback browser checks | **4 workflows passed**, including final return-scroll and SVG-geometry assertions |
| Documentation links and whitespace | **56 Markdown files, 286 local links/anchors passed**; whitespace check passed |

[Backend tests](../../../../backend/tests/Feature/FeedbackReadingTest.php) cover full content, privacy, requester/office isolation, guest references, pagination beyond 100 records, filter counts, literal wildcard search, Manila date boundaries, invalid input and final-assignment context.

[Browser tests](../../../../frontend/tests/feedback-revamp.spec.ts), together with [full-reading regression](../../../../frontend/tests/feedback-reading.spec.ts), exercise real submission/filter/detail APIs, refresh/Back state, full multiline content, compact pill alignment, Not rated/Not supplied, mobile jump/reflow, related-service navigation, no-results and retry recovery. A synthetic 503 is intercepted only to test the failure UI. Tests run on an isolated app/database; ordinary local/public-demo records are not modified.

Desktop/mobile screenshots are generated locally under `.local/feedback-review/`. Visual inspection is scoped to feedback; the existing application shell is preserved. Automated reflow checks cover 390 px and 320 px widths. These checks are not participant usability research, a full accessibility certification or proof of production readiness.

## Handoff and limits

Open Feedback & ratings in the local administrator workspace. Existing installations require no database migration. The device-hosted public demo serves a separate built artifact and was not rebuilt or redeployed by this task.

Search is ordinary database substring filtering, not full-text indexing or semantic analysis. Current record names/assignment context use the existing prototype data model. Cross-device scroll synchronization, saved filters, a desktop reading pane and production hosting remain outside this change.

[Sprint overview](sprint-overview.md) · [Iteration overview](../iteration-overview.md) · [Agile sprint](../../../agile/sprints/EPIC-002/sprint-02/sprint-02.md)
