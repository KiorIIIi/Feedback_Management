# Documentation map

Reorganized 21 October 2026 to follow the supplied folder reference. Existing records were moved, links updated and binary architecture exports preserved unchanged.

## Placement rules

- `agile/`: epic definitions, backlog and retrospective/future sprint records.
- `architecture/`: technical baseline, database design and historical exports.
- `changes/`: iteration folders containing sprint folders and separate numbered change records.
- `project/`: implemented decisions and project-navigation guidance.
- `requirements/`: supplied research and requirement traceability.
- `testing/`: actual verification results, audits and review dispositions.

All new Markdown documentation must be placed inside `docs/`, grouped by purpose. Do not create new Markdown files at the repository root or alongside application code. Keep indexes, filenames and cross-references organized for developer handoff.

The root `README.md`, `AGENTS.md`, `PRODUCT.md`, `DESIGN.md` and code-adjacent MCP README predate this rule. Their current locations are recorded as existing entry points, not a precedent for creating more Markdown outside `docs/`.

## Previous and current locations

Old paths below are relocation history, not links to files that still exist.

| Previous path | Current location |
| --- | --- |
| `docs/system-architecture.md` | [docs/architecture/system-architecture.md](../architecture/system-architecture.md) |
| `docs/database-design.md` | [docs/architecture/database-design.md](../architecture/database-design.md) |
| `docs/database-normalization.md` | [docs/architecture/database-normalization.md](../architecture/database-normalization.md) |
| `docs/implementation-notes.md` | [docs/project/implementation-notes.md](implementation-notes.md) |
| `docs/iteration-1.md` | [docs/changes/iteration-01/sprint-01/001-reporting-and-feedback.md](../changes/iteration-01/sprint-01/001-reporting-and-feedback.md) |
| `docs/verification.md` | [docs/testing/verification.md](../testing/verification.md) |
| `docs/bug-audit.md` | [docs/testing/audits/bug-audit.md](../testing/audits/bug-audit.md) |
| `docs/security-review.md` | [docs/testing/audits/security-review.md](../testing/audits/security-review.md) |
| `docs/security-audit.md` | [docs/testing/audits/security-audit.md](../testing/audits/security-audit.md) |
| `docs/security-audit-follow-up.md` | [docs/testing/audits/security-audit-follow-up.md](../testing/audits/security-audit-follow-up.md) |
| `docs/build_architecture.py` | [docs/architecture/scripts/build_architecture.py](../architecture/scripts/build_architecture.py) |
| `docs/CLSU_System_Architecture.docx` | [docs/architecture/exports/CLSU_System_Architecture.docx](../architecture/exports/CLSU_System_Architecture.docx) |
| `docs/_architecture_qa/architecture.pdf` | [docs/architecture/exports/_architecture_qa/architecture.pdf](../architecture/exports/_architecture_qa/architecture.pdf) |
| `docs/_architecture_qa/architecture.png` | [docs/architecture/exports/_architecture_qa/architecture.png](../architecture/exports/_architecture_qa/architecture.png) |
| `docs/_architecture_qa/page-1.png` | [docs/architecture/exports/_architecture_qa/page-1.png](../architecture/exports/_architecture_qa/page-1.png) |
| `docs/_architecture_qa/page-2.png` | [docs/architecture/exports/_architecture_qa/page-2.png](../architecture/exports/_architecture_qa/page-2.png) |
| `docs/_architecture_qa/page-3.png` | [docs/architecture/exports/_architecture_qa/page-3.png](../architecture/exports/_architecture_qa/page-3.png) |
| `docs/_architecture_qa/page-4.png` | [docs/architecture/exports/_architecture_qa/page-4.png](../architecture/exports/_architecture_qa/page-4.png) |
| `docs/_architecture_qa/page-5.png` | [docs/architecture/exports/_architecture_qa/page-5.png](../architecture/exports/_architecture_qa/page-5.png) |
| `docs/_architecture_qa/page-6.png` | [docs/architecture/exports/_architecture_qa/page-6.png](../architecture/exports/_architecture_qa/page-6.png) |
| `docs/_architecture_qa/page-7.png` | [docs/architecture/exports/_architecture_qa/page-7.png](../architecture/exports/_architecture_qa/page-7.png) |
| `docs/_architecture_qa/page-8.png` | [docs/architecture/exports/_architecture_qa/page-8.png](../architecture/exports/_architecture_qa/page-8.png) |
| `docs/_architecture_qa/page-9.png` | [docs/architecture/exports/_architecture_qa/page-9.png](../architecture/exports/_architecture_qa/page-9.png) |

The research source was copied unchanged from the previously supplied `ITRM 3100.md` into [requirements/source/itrm-3100.md](../requirements/source/itrm-3100.md). The external original was not modified.

Use [the documentation index](../README.md) for reading order and `python scripts/check-docs.py` for local links/anchors. Generated private exports and current feedback screenshots stay under ignored `.local/`, not the documentation tree.
