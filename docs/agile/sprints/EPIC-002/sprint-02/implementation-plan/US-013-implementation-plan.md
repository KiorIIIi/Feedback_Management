# US-013 — Feedback browsing and reading revamp

Iteration 02 / Sprint 02. Requested 21 October 2026.

As an authorized reviewer, I can browse either feedback type, narrow results and open a readable complete submission without losing my browsing context.

## Accepted scope

- Implement the user-selected Figma Option A: list and separate detail pages.
- Preserve the latest compact headings and source pills beside ratings, readable body/metadata and the existing shell.
- Default to General feedback with separate counts and real search/rating/date/category filters.
- Keep independent feedback, supplied contact facts and unverified references separate from verified service evaluations.
- Expose authorized service context and final assignee using existing data/assignment semantics.
- Preserve query/page/scroll context; provide loading, error, empty and mobile reading behavior.

## Implementation

Use scoped React/CSS, the existing cancellable loading hook, local Figma assets and shared API components. Extend FeedbackReader and controller validation for read filtering/context. Preserve server access restrictions and existing pagination response compatibility. No schema or workflow changes are needed.

## Acceptance and verification

Find a low-rated submission; identify its type; read full multiline content and supplied/service context; return with filters/position retained. Test missing ratings/contact fields, long references, 320/390 px reflow and failure recovery. Backend tests must cover ownership, MISO/handling-office scope, filtered totals, literal searches, date bounds and final assignments on SQLite and MySQL.

See the [canonical change record](../../../../../changes/iteration-02/sprint-02/001-feedback-ui-revamp.md) and [story verification](../sprint-02-tests-US-013.md). Option B, moderation/replies, read tracking, AI and deployment are excluded.
