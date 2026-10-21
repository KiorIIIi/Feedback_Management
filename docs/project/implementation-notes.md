# Rough implementation · 20 October 2026

## Implemented

The project now contains a React/TypeScript/Vite frontend and Laravel 12 API backed by MySQL. The saved architecture and logical database documents remain the planning baseline. This document records the narrower, implemented prototype and its provisional decisions.

Implemented flows: session sign-in/out; public independent feedback; authenticated service submission; own-request tracking; office-scoped MISO review; eligibility-checked assignment; priority review; clarification with preserved original statements and revision history; personnel progress; human completion with actions and outcome; one optional verified evaluation; private in-app notices; scoped metrics; and queued private PDF, Excel and CSV exports. MCP has a disabled UI slot and documented adapter boundary only.

## User-delegated prototype policy

The user delegated rough-version decisions on 20 October. These choices are configurable drafts, not MISO approval:

- One active assignee per request, enforced by request-row locking and replacing assignments within the same transaction. Historical assignment rows remain.
- Optional requesting-office affiliation; when supplied, it must be one of the caller's memberships. Handling office defaults to MISO, independently of requesting office.
- Priority versions contain shared effective dates; matrix cells have a unique version/urgency/impact key. The synthetic `demo-v1` matrix is seeded. Requester urgency/impact is retained separately. Official priority starts unset and requires staff review; exceptions require reasons.
- Explicit permitted transitions in `backend/config/workflow.php`. Clarification returns to Pending through the owner's recorded reply. Completion requires actions and a service outcome.
- Five-point evaluation scale matches Figma. Verified evaluations are request-keyed and transactionally checked for ownership and completion. General feedback job numbers are unverified text.
- Reports use calendar days in Asia/Manila and requests submitted during the selected period. Completed counts are the current completed subset of that cohort, grouped by submission day. Satisfaction excludes general feedback and includes the verified-rating count. No response-time targets are invented.

## Service evaluation reporting implemented on 20 October

The paper's Automated Report Generation feature is implemented under **Reports & analytics**. Administrators choose a rolling calendar-day window or one calendar month, review category/priority/status/personnel summaries, verified ratings and elapsed resolution averages, and request private queued PDF or native Excel (`.xlsx`) exports. CSV remains a request-register format. `ServiceEvaluationReport` supplies the same calculations to the screen and all exports; `ReportRenderer` uses pinned Dompdf 3.1.6 and PhpSpreadsheet 5.10.0. PHP GD and DOM/XML support are required in addition to the installation requirements.

The user explicitly selected a separate staff-assigned category. Administrators create office-scoped categories on the report page and assign or clear them while reviewing an open request. Categories are independent of service types. No taxonomy is seeded or claimed as MISO-approved. Completed requests remain immutable, and existing records remain Uncategorized. Category changes use the existing expected-version check and commit the request, internal event and audit snapshot together. Personnel and requesters cannot assign categories. Creation is serialized by handling-office row lock, names are normalized for whitespace, and duplicates are rejected within that office. Rename/delete and bulk recategorization are outside this release.

Report definitions are visible in the screen, PDF and Excel Summary sheet:

- The cohort is requests **submitted** during the selected period, in Asia/Manila. Month bounds are inclusive at the start and exclusive at the following month's start; future submissions are excluded. Statuses, categories, ratings and assignments reflect generation time, not historical month-end state.
- Each request appears once in every breakdown. Open work uses its current active assignee; completed work uses its final assignment. Unassigned, Uncategorized and Unreviewed are explicit groups.
- Average verified rating = sum of available verified ratings / rating count, on completed requests in that cohort. Missing ratings and independent general feedback are excluded. The existing prototype scale is 1–5.
- Average resolution hours = sum of valid completion-minus-submission calendar durations / eligible completed requests. Open requests and missing, negative or future completion times are excluded. The eligible denominator and excluded completed count are disclosed. This does not implement business hours, response deadlines or SLA compliance.
- Recurring concerns are repeated subjects after case and whitespace normalization, with at least two occurrences and up to five example job numbers. This is deterministic exact-text grouping, not AI or semantic clustering.
- Empty averages display an em dash, never a misleading zero. PDF and Excel include summaries, definitions, breakdowns, recurrence and a request register; CSV contains the register. Internal notes and contact details are omitted. Excel strings are explicitly text cells and CSV formula prefixes are neutralized.
- Report jobs remain pending/processing until the private file is stored successfully. Job status/download recheck requester identity, active administrator role and handling office. Failed or missing files provide no download link. Completed job retries reuse the stored result.

Reports are generated on demand through the queue. Automatic scheduled delivery, outbound email, MCP report tools and ARTA certification are not implemented. The paper does not establish the final campus reporting policies; current definitions remain explicit prototype choices.

## Physical schema refinements and intentional summaries

`priority_rule_versions` resolves the effective-date dependency described in the normalization review. `request_events` represents append-only status history, clarification messages, and progress notes in a typed chronological stream; metadata can retain action-specific details. `request_revisions` preserves original snapshots and clarification replies. Current request status/timestamps/priority and immutable audit snapshots intentionally summarize historical events; the workflow commits these together.

For this rough version, operational roles use a controlled `users.role` field and a primary `office_id`; office memberships and service eligibility use relational junctions. This is a smaller access model than the proposed full roles/permissions schema. Fine-grained grants, multi-office administrative scope, availability history and complete configuration administration are deferred. There is no claim of fully verified 3NF for the entire schema.

Future agent authorization and scope tables are intentionally not created without an active integration. Their required relational design and provenance/idempotency rules are recorded at `backend/app/Integrations/Mcp/README.md`.

## Figma implementation

Design file: PeQN6nVBQbPNXWoTU32Mhm. High-fidelity source responses are retained in `design-reference/*.tsx.txt`; original asset exports and provenance are cataloged in `design-reference/assets.json`. All 262 exported assets are local under `frontend/public/assets/figma/`. Plus Jakarta Sans is installed and self-hosted by the build.

The implementation follows the green/gold admin workspace, split campus welcome/sign-in, requester navigation, dashboard, forms, detail/timeline and rating layouts. Data-driven text, dates, job numbers, counts, and charts come from the API rather than copying hard-coded Figma metrics. The assistant status is intentionally disconnected. The supplied admin design does not define every narrow-screen layout; these adapt its components for mobile. Pixel-perfect equivalence across every Figma frame has not been certified.

## Role workspaces

The requester home now follows the explicitly selected middle `Your dashboard` frame (`26:22477`): exact exported requester icons, four personal metrics including unrated completed jobs, clarification banner, four recent requests, rating prompt, latest notifications and help panel. Its dedicated authenticated dashboard service calculates all-time owner-scoped counts rather than reusing the admin's period-filtered report metrics. Text, job references and dates remain real local data; long references may wrap. Desktop composition follows the supplied frame and mobile behavior adapts it without a supplied mobile dashboard frame.

Role landing pages now separate MISO administration (`/app/admin`), assigned personnel work (`/app/work`), and the requester dashboard (`/app/requester`). The supplied Figma page contains requester screens, admin screens and personnel sign-in; its PERSONEL frame is the admin staff directory, not a separate personnel work dashboard. The personnel work view composes the existing portal components for the architecture's assignment/progress responsibilities and is not claimed as a direct copy of a missing Figma frame. Personnel do not see requester submission or evaluation prompts. Selected-role login is checked by server authentication; query parameters cannot grant a role.

The landing page combines administrator and personnel access into **Staff login** (`/login?role=staff`). Both use the same form and are redirected using the authenticated account's stored role: admin → `/app/admin`, personnel → `/app/work`. Requester access remains under Request a service and leads to `/app/requester`. Staff sign-in rejects requester credentials before creating a session. Operational account role determines routing and authorization; faculty/student classification does not. Existing role-specific URLs remain supported for compatibility.

The [20 October bug audit](../testing/audits/bug-audit.md) records implemented corrections to internal-note privacy, active sessions, personnel access after transfer/reassignment, complete-origin validation, effective priority versions, notification retries, frontend failure recovery, report polling, timestamp parsing, and launcher ownership/readiness checks. No schema changes or new approved workflow policies were introduced.

The final Gemini review added explicit safe model field lists, response headers, configurable production session encryption/secure-cookie defaults, and an isolated Playwright app. Signed-in feedback remains account-linked with an explicit notice; guest feedback is unlinked. An anonymous signed-in mode has not been approved or added. See [security review](../testing/audits/security-review.md) for the final report dispositions and deployment boundaries.

## Feedback UI revamp · Iteration 02 / Sprint 02

The user selected Figma Option A (list and separate detail pages). Feedback now opens on General feedback, with separate tab counts, real server-side search/rating/date/category filtering, compact readable rows and a responsive full-message reader. Source pills sit at the top right beside numeric/star ratings. Detail links preserve URL filters/pages; session-only per-account scroll offsets restore browsing position. Verified details expose authorized service/office/final-assignee/completion/request-category context. General contact fields remain as supplied and typed references remain unverified text. No schema, workflow or deployment changes were made. See the [Iteration 02 change and verification record](../changes/iteration-02/sprint-02/001-feedback-ui-revamp.md).

## Deferred scope

The [Iteration 1 log](../changes/iteration-01/sprint-01/001-reporting-and-feedback.md) records the reporting and feedback-reading additions. Feedback history now paginates both independent submissions and verified evaluations. Shared `FeedbackReader` queries enforce the same MISO-only routing, requester ownership and administrative handling-office scope for the list and full-message detail routes. General-feedback detail includes optional contact fields as supplied, without treating a typed job number as verified or granting private request access. Personnel access remains denied. Messages are rendered as escaped text with preserved line breaks; they cannot authorize application actions.

The [latest security audit follow-up](../testing/audits/security-audit-follow-up.md) adds non-development startup validation, explicit CORS/proxy configuration, environment-aware logging, Unicode CSV hardening and read throttling. Remembered sign-in is now opt-in. Transactional in-app notification rows remain atomic with their workflow; no external notification worker is dispatched before commit.

Attachments and private file uploads; email delivery; password recovery/provisioning; runtime settings and system evaluation; production retention and restoration; background maintenance; response and resolution SLA metrics; advanced role/permission administration; provider/model integration and MCP execution. In-app notifications and report queue jobs work without any AI integration.

This is a local prototype with synthetic demo accounts, not a production deployment. Do not load personal or operational campus data until identity provisioning, final policies, hosting, retention, backups, and security hardening are approved.
