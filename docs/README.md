# Documentation

Active development branch: **`front-main`**. Follow the user's [development and publishing workflow](project/development-workflow.md) until final merge approval.

Start here for the CLSU Feedback Management System and Service Tracker. The folders follow the supplied reference: agile work records, architecture, changes, project guidance, requirements and testing.

| Folder | What belongs here | Start with |
| --- | --- | --- |
| [agile](agile/README.md) | Epics, backlog, sprint records, implementation plans and story verification | [Product backlog](agile/product-backlog.md) |
| [architecture](architecture/README.md) | Architecture, database design, normalization and historical exports | [System architecture](architecture/system-architecture.md) |
| [changes](changes/README.md) | Iteration logs and documentation changes | [Iteration 1](changes/iteration-01/iteration-overview.md) |
| [project](project/README.md) | Setup references, implementation decisions and documentation conventions | [Implementation notes](project/implementation-notes.md) |
| [requirements](requirements/README.md) | Research source, requirements and traceability | [Requirements](requirements/system-requirements.md) |
| [testing](testing/README.md) | Test commands, recorded results, bug reviews and security audits | [Verification history](testing/verification.md) |

For the original reporting delivery, read [Iteration 1](changes/iteration-01/iteration-overview.md), then the [EPIC-002 sprint record](agile/sprints/EPIC-002/sprint-01/sprint-01.md). Those records organize existing implementation evidence retrospectively; they do not claim earlier sprint ceremonies or MISO approval.

The latest backend addition is the [MCP read-only foundation](architecture/mcp-implementation.md), recorded in [Iteration 02 / Sprint 02](changes/iteration-02/sprint-02/005-mcp-read-foundation.md). It is opt-in and does not connect the embedded assistant or expose operational writes.

The original paper is preserved under [requirements/source](requirements/source/itrm-3100.md). Word/PDF architecture exports and their rendered review pages are under [architecture/exports](architecture/README.md#historical-exports). Current implementation status is recorded in Markdown; historical exports can lag behind it.

[Repository setup](../README.md), [agent instructions](../AGENTS.md), [product context](../PRODUCT.md) and [design context](../DESIGN.md) stay at the repository root for their existing tools and entry points. Private credentials, report downloads and current public-demo screenshots remain in ignored `.local/` storage.

See the [documentation map](project/documentation-map.md) for the old-to-new locations. Run `python scripts/check-docs.py` from the repository root to check local documentation links and Markdown heading anchors.
