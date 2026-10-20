# Documentation organization — 21 October 2026

Request: Arrange all project documentation like the supplied folder reference.

## Changes

- Added the six top-level groups: `agile`, `architecture`, `changes`, `project`, `requirements` and `testing`.
- Added a documentation index, folder indexes, definitions, product backlog, three epic records and a sprint template.
- Organized Iteration 1 under EPIC-002 / Sprint 01, with four retrospective implementation plans and four story verification records (US-007 through US-010). The canonical iteration log remains in `changes/iteration-01/sprint-01/001-reporting-and-feedback.md`.
- Moved existing architecture/database documents, implementation notes, change log, test history and audit records to their matching groups.
- Moved the Word architecture export, historical PDF, figure and rendered pages under `architecture/exports/`, preserving their bytes. Relocated the historical generator under `architecture/scripts/` and adjusted its output directory without running it.
- Copied the user-supplied research draft unchanged into `requirements/source/itrm-3100.md`; added requirement-to-implementation traceability separately.
- Updated Markdown links and root README/AGENTS/PRODUCT references, and removed a duplicate security-review paragraph from the root README.
- Added `scripts/check-docs.py` for local documentation links and heading anchors.

Root tool entry points (`README.md`, `AGENTS.md`, `PRODUCT.md`, `DESIGN.md`) and code-adjacent MCP guidance remain in place. The [documentation map](../../../project/documentation-map.md) lists every relocated file.

## Verification and boundaries

This is a documentation-only change. Application behavior, database contents, live hosting, credentials and generated private report files are outside its scope. Application tests do not need to be rerun solely for rearranging documentation.

Local links/heading anchors, relocated-file inventory, preservation checksums and Python syntax are checked for this change. Historical implementation test results remain dated evidence; this reorganization does not turn them into a new test run. No Word/PDF regeneration, research-claim verification, sprint ceremonies or MISO approval is asserted.

Recorded checks:

| Check | Result |
| --- | --- |
| Documentation links and Markdown anchors | 44 Markdown files, 235 local links/anchors passed |
| Original file inventory | All 23 relocated files accounted for |
| Word/PDF/image preservation | All 12 binary files matched their pre-move SHA-256 checksums |
| Research source preservation | Repository copy and untouched external original matched |
| Python syntax | Documentation checker and relocated generator parsed successfully; generator was not run |
| Stale root documentation paths | No unintended references remained outside the deliberate relocation-history table |
| Whitespace checks | `git diff --check` passed |
