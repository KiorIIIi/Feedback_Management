# 002 — Borderless status and priority labels

Date: 21 October 2026, Asia/Manila.

Iteration 02 / Sprint 02. Follow-up visual refinement; no added operational feature.

## Request and design decision

The user requested removing the outlined status/priority badges throughout the interface and using Impeccable to make the presentation less decorative. The supplied request-table screenshot is the reference for the affected labels.

Applied Impeccable's quieter guidance for operational interfaces: reduce competing borders and saturation, retain readable text and semantic color, and use consistent sizing and spacing. The existing CLSU identity and status meanings remain.

## Changes

- Remove all status/priority badge borders. Use restrained tinted fills, darker matching text, 6 px corners and no shadows.
- Standardize shared labels to 12 px medium text, 18 px line height and 3 × 8 px padding. Remove the small-table and mobile overrides that reduced labels to 10 px.
- Keep the existing status dot and requester-dashboard icons; status meaning remains visible in text, independently of color.
- Share the same neutral, red, amber, green, blue, purple and orange tokens between request-list/detail/dashboard badges and the requester dashboard.
- Remove the outline from feedback source pills and align their fill/text with the shared green labels. Keep their approved compact pill shape and top-right position beside ratings. The completed-service context label uses the same borderless green treatment.

Coverage: Unreviewed, Critical, High, Medium, Low, Pending, On Hold, Assigned, In Progress, Completed and For Clarification; plus Independent feedback / Verified evaluation source labels. Shared components propagate the treatment to admin, personnel and requester screens.

This changes the earlier outlined source-pill styling recorded in [001](001-feedback-ui-revamp.md). It does not remove table dividers, field borders or panel structure; the request concerns badges. No status text, business rules, API, database, dependencies or deployment changed. No Figma edits were made in this follow-up.

## Implementation

- [styles.css](../../../../frontend/src/styles.css): shared badge palette, borderless component and status variants.
- [requester-dashboard.css](../../../../frontend/src/requester-dashboard.css): retain icon sizing; remove duplicate status colors/type overrides so shared styling applies.
- [feedback.css](../../../../frontend/src/feedback.css): borderless source/context labels using shared green tokens.

## Verification

Calculated sRGB text/background contrast:

| Label family | Ratio |
| --- | --- |
| Neutral | 5.79:1 |
| Critical | 5.70:1 |
| Pending / High / On Hold | 5.63:1 |
| Completed / Medium | 6.21:1 |
| In Progress / Low | 5.90:1 |
| Assigned | 5.92:1 |
| For Clarification | 5.58:1 |

All exceed 4.5:1 for normal text. This is a color-pair check, not a full accessibility certification.

| Check | Result |
| --- | --- |
| TypeScript + Vite production build | Passed |
| Existing feedback, requester-dashboard and responsive browser checks | 7 passed; 1 failed on the separate public welcome illustration resize assertion |
| Direct request-list inspection | 14 badges checked at 1440 px and 390 px: all zero-width borders and 12 px text |
| Visual inspection | Desktop/mobile request-list captures and requester dashboard inspected; no badge changes needed after inspection |
| Documentation link checker | 57 Markdown files, 293 local links/anchors passed |

The public welcome test expected the campus scene width to fit a roughly 276 px illustration wrapper; it observed 362 px. This failure is outside the badge selectors and remains unresolved; the complete browser run is not claimed as passing. No welcome illustration code was changed.

Existing browser checks are reused; no implementation-mirroring tests were added for this CSS-only refinement. Backend tests are unnecessary because no backend behavior changed. Browser tests and direct inspection use the existing isolated app/database, not ordinary demo records. Request tables retain their intentional internal horizontal scrolling on mobile.

Local visual evidence: `.local/badge-review/requests-1440.png`, `requests-390.png` and `verification.json`; requester screenshots are generated under `frontend/test-results/`. No build artifact was deployed.

[Sprint overview](sprint-overview.md) · [Iteration overview](../iteration-overview.md)
