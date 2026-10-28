# 005 — Feedback UI evaluation

Date: 21 October 2026

Iteration: 01 / Sprint: 01

Status: Editable Figma alternatives prepared for evaluation; application implementation is not authorized by this delivery.

## Scope and boundary

The user requested stronger readability and information hierarchy for the feedback list, general-feedback details and verified service-evaluation details. They subsequently reiterated **design only; do not implement yet**.

A new **Feedback Review — UI Evaluation** page was added to the existing Figma file. Original pages, frames and university assets were preserved. Application source, API, schema, dependencies and deployment were not changed. Search/filter behavior and service navigation are prototype simulations.

## Open the alternatives

| Alternative | Editable design | Prototype starting point | Main tradeoff |
| --- | --- | --- | --- |
| A — List and detail pages | [Option A](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-181) | [Desktop prototype](https://www.figma.com/proto/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?page-id=102%3A148&node-id=102-181&starting-point-node-id=102%3A181&scaling=scale-down) | Dedicated reading area and adjacent desktop context; opening another record requires returning to the list. |
| B — Inbox and reading pane | [Option B](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-187) | [Desktop prototype](https://www.figma.com/proto/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?page-id=102%3A148&node-id=102-187&starting-point-node-id=102%3A187&scaling=scale-down) | List remains visible while reading; independent pane scrolling and context below the message require more attention. |

[Evaluation notes and handoff](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=111-2408) are also editable in Figma. Four named flows cover A/B on desktop/mobile. Select the desired flow in Present mode.

No layout has been selected for application implementation.

## Primary frames

Desktop frames are 1440 × 1100; mobile frames are 390 × 844 with vertically scrolling content.

| Option | Screen | Desktop | Mobile |
| --- | --- | --- | --- |
| A | Feedback list, General feedback initially selected | [List](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-181) | [List](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-184) |
| A | General feedback details | [Details](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-182) | [Reading screen](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-185) |
| A | Service evaluation details | [Details](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-183) | [Reading screen](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-186) |
| B | Inbox with no selection / mobile list | [Inbox](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-187) | [List](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-190) |
| B | General feedback selected | [Reading pane](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-188) | [Reading screen](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-191) |
| B | Service evaluation selected | [Reading pane](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-189) | [Reading screen](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=102-192) |

The page also contains 41 supporting navigation/filter/record frames, a component board, state examples, mobile context examples and evaluation notes. Supporting frames allow each displayed record to open its own matching synthetic content.

## Shared design decisions

- Retain CLSU green/gold, the existing university seal and Plus Jakarta Sans. Use dark ink for messages, deep green links/actions and gold accents.
- Use 30 px page titles, 20 px section headings, 16/26 px body text and 14/21 px metadata. Message components cap desktop measure at 600 px, approximately 65–75 characters for typical lines, and fill the available mobile width.
- Separate General feedback and Service evaluations into counted tabs. The synthetic dataset contains six general submissions and three evaluations in both alternatives.
- Provide proposed search, rating/date filters and a General-feedback category filter. **Feedback category** describes independent feedback; **Request category** is the existing separate staff-assigned service-request classification.
- Use subject, date, type, preview, numeric rating/stars and explicit opening actions in rows. Unrated general feedback says **Not rated**.
- General feedback uses **Contact name · as supplied**, **Contact email · as supplied** and **Not supplied** for absent fields. No identity is inferred.
- General job references remain explicitly unverified text with no private-service link.
- Verified evaluations show job number, service type, requesting office, final assigned personnel, completion date and request category. A missing request category says **Uncategorized**.
- Mobile has one full-width reading screen and a jump-to-context action. It does not compress an inbox and reading pane into two columns.

Twenty-four scoped native component templates/variants cover tabs, filters, search, buttons, ratings, rows, metadata and reading sections. They use editable text/component properties, auto-layout, scoped variables and text styles. Screen content is not flattened into images. The original CLSU seal is reused as an image asset.

## Content and simulated interactions

Both options use the same invented subjects, messages, names and dates. Contact emails use the reserved `example.invalid` domain. No live feedback messages or contact details from the screenshots were copied.

Search/filter controls demonstrate preset outcomes rather than editable input or a full filtering engine:

| Tab | Demonstrated preset | Result |
| --- | --- | --- |
| General feedback | Wi-Fi; rating 1–2; last 30 days; Complaint | G-001, Wi-Fi drops during online examinations |
| Service evaluations | Network; rating 1–2; last 30 days | E-002, Network inspection in the library |

Tabs, record opening, filter application/clearing, Back, mobile jump actions and the simulated service boundary are connected. Active tabs and already-selected records have no redundant self-navigation. The service action opens a prototype explanation, not a real request.

Dedicated filtered states preserve the chosen type and preset when selecting a record in the desktop inbox. A and mobile details use Back history; navigation sets scroll-reset to false. Only page 1 is represented. Arbitrary filter combinations, real pagination, browser URL state and exact Figma-player scroll restoration remain unverified.

[State examples](https://www.figma.com/design/PeQN6nVBQbPNXWoTU32Mhm/FEEDBACK-MANAGEMENT?node-id=106-3729) include loading skeletons, empty results, no matches, load error/retry, missing contact, Not rated, long messages/references and a visible focus outline. These are review examples rather than production error handling. Mobile context examples expose the complete sections that normally sit below the reading viewport.

## Evaluation using identical tasks

This was a designer inspection of screenshots, editable layers and the prototype graph. No participant sessions or timed usability study were conducted.

| Task | Option A observation | Option B observation |
| --- | --- | --- |
| Find low-rated feedback | Rating controls lead to a single preset result; the rating and explicit opening action align beside each desktop row. | Same preset and ratings; the result can be read while its row remains visible. |
| Identify general feedback versus verified evaluation | Counted tabs, type labels and verification badges separate the sources. General references do not grant access. | Same distinctions, with a selected-row appearance alongside the reading pane. |
| Read a long message and find contact/service context | Desktop context is adjacent to the message; mobile uses a jump action to reach the complete context below it. | Desktop context follows the message within the scrolling reader. Mobile uses the same full-width reading approach as A. |
| Return without losing browsing context | Back reactions return through prototype history; type/filter state is represented by distinct origin frames. Exact scroll restoration needs player review. | The list stays alongside the reader. Filtered selection states retain the displayed preset; pane/list scroll restoration still needs player review. |

A favors focused reading and immediate desktop context. B favors reviewing several records while retaining visual list context. Mobile filters occupy substantial vertical space in both options; evaluators should assess whether collapsible filters are desirable before implementation.

## Checks performed and limitations

- Viewed screenshots of all 12 primary frames, plus state/missing-contact examples, evaluation notes and full mobile context sections. Corrected clipping caused by fixed component/text heights, aligned mobile tab heights and constrained reading width.
- Final structural inspection found **zero horizontal overflow cases across the 12 primary frames**. Message text uses height-based wrapping, body size is 16 px, and the smallest text in the primary frames is 14 px.
- Confirmed 24 native components, four named flows, and 275 connected interaction sources before adding the two context inspection clones. Every referenced destination in that graph existed. This verifies graph structure, not an end-to-end presentation-player run.
- Confirmed the original `Page 1` and `Components · Portal UI` pages remain alongside the new evaluation page.
- Inspected full synthetic service references on mobile: they wrap without truncation or shrinking. Missing-contact and Not rated examples remain explicit.
- Focus/selection appearance is shown in the state board. Keyboard traversal, screen-reader semantics, browser zoom/reflow, assistive technology and runtime error behavior require later implementation checks. Full accessibility compliance is not claimed.
- No application build, backend tests, browser app tests or deployment were needed or run for this design-only change.

Calculated sRGB contrast ratios for the selected normal-text pairs exceed the [WCAG 4.5:1 minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html):

| Foreground / background | Ratio |
| --- | --- |
| Ink `#1E3029` / white | 13.91:1 |
| Secondary text `#52665C` / white | 6.15:1 |
| Deep green `#005B30` / white | 8.27:1 |
| Ink / page `#F5F8F6` | 13.01:1 |
| Secondary text / page | 5.75:1 |
| Deep green / soft green `#EAF3ED` | 7.30:1 |
| White / deep-green active controls | 8.27:1 |

Gold stars are redundant visual accents; numeric ratings supply the readable value. These color calculations do not establish accessibility compliance for an implemented application.

## Future implementation handoff

Inspection of [FeedbackReader.php](../../../../backend/app/Services/FeedbackReader.php) confirms that current list filters only select pagination pages. Evaluations join requests for subject/job number; the reader does not currently return the additional service context shown in these designs.

After the user selects a layout, a separately authorized implementation will need:

1. Server-side search and rating/date/feedback-category filters with pagination counts and deterministic ordering, preserving existing requester/handling-office/MISO access restrictions.
2. Authorized service type, requesting office, final assigned personnel, completion date and request-category fields. Define final-assignment semantics against assignment history before exposing them.
3. UI state for active tab, filters, page/position and selected record, plus loading/error states and accessible control semantics.
4. Continued separation of supplied contact facts, unverified general references and owner-submitted verified evaluations.

No reply, moderation, read/unread tracking, sentiment analysis or operational workflow changes are included. This record changes no accepted application schema/workflow decision; it records proposed presentation alternatives.

Documentation validation: `python scripts/check-docs.py` passed for 50 Markdown files and 253 local links/anchors; `git diff --check` also passed. After a final prose-only update with no link changes, rerunning these commands and checking the new file separately could not start because the local MXC launcher reported `CreateProcessSecurityEnvironment failed (HRESULT = 0x80070020)`. The final rerun is therefore unverified, rather than a documentation-check failure. Repository changes for this delivery are limited to this record and the sprint/iteration overviews.

[Sprint overview](sprint-overview.md) · [Iteration overview](../iteration-overview.md)

## Subsequent authorization

On 21 October 2026, the user selected Option A and authorized application implementation. That later delivery is recorded separately under [Iteration 02 / Sprint 02](../../iteration-02/sprint-02/001-feedback-ui-revamp.md). This evaluation record remains the historical design-only delivery.
