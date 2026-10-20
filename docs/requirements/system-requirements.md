# Requirements and traceability

Source: [ITRM 3100 research draft](source/itrm-3100.md), the [architecture baseline](../architecture/system-architecture.md), and explicit user decisions recorded in [implementation notes](../project/implementation-notes.md). This is a navigation summary, not a replacement specification or new policy approval.

| Requirement | Current state | Traceability |
| --- | --- | --- |
| Authenticated private request submission and tracking | Implemented prototype baseline | US-001 / US-002, [access rules](../architecture/system-architecture.md#user-interfaces-and-access) |
| Request clarification, prioritization, eligible assignment and progress history | Implemented prototype baseline | US-003 / US-004 / US-005, [workflow](../architecture/system-architecture.md#service-workflows) |
| Authorized human completion and optional owner evaluation | Implemented prototype baseline | US-005 / US-006, [prototype policy](../project/implementation-notes.md#user-delegated-prototype-policy) |
| Keep general feedback independent from verified service evaluation | Implemented prototype baseline | US-006, [feedback visibility](../architecture/system-architecture.md#feedback-and-visibility) |
| Summarize requests by category, priority, status and assigned personnel | Implemented in Iteration 1 | US-007 / US-008, [report definitions](../project/implementation-notes.md#service-evaluation-reporting-implemented-on-20-october) |
| Average verified rating and resolution duration; PDF/Excel reports | Implemented in Iteration 1 | US-007 / US-009, [Iteration 1](../changes/iteration-01/sprint-01/001-reporting-and-feedback.md) |
| Separate category assigned by staff | Explicit user decision, implemented in Iteration 1 | US-008, [category schema](../architecture/database-design.md#implemented-category-extension--20-october-2026) |
| Read complete private feedback and supplied contact details; reach older records | User-requested addition implemented in Iteration 1 | US-010, [feedback plan](../agile/sprints/EPIC-002/sprint-01/implementation-plan/US-010-implementation-plan.md) |
| Final priority matrices, business hours/deadlines, moderation and campus release policy | Remain unresolved; current demo choices are labeled provisional | [MISO decisions](../architecture/system-architecture.md#decisions-requiring-miso-validation) |
| Production operations and connected MCP/AI behavior | Deferred | US-011 / US-012, [deferred scope](../project/implementation-notes.md#deferred-scope) |

For exact acceptance criteria and the checks that actually ran, use the [EPIC-002 sprint record](../agile/sprints/EPIC-002/sprint-01/sprint-01.md) and [verification history](../testing/verification.md). Scheduled email, semantic concern clustering and SLA compliance must not be inferred from the implemented on-demand report feature.
