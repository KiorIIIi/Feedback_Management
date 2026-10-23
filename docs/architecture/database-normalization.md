# CLSU Feedback Management System — Database Normalization

Status: Logical design assessment; full 3NF has not been established  
Last updated: 19 October 2026.

Related documents: [System Architecture](system-architecture.md) and [Database Design](database-design.md).

## Assessment

The proposed core tables follow a largely normalized structure. The full model is a suitable starting point, but it must not yet be described as a fully verified Third Normal Form (3NF) schema. Its core overview shows relationships rather than a complete set of attributes and functional dependencies, so that diagram alone cannot prove normalization.

The recommended target is 3NF for operational and configuration data, with explicit, justified exceptions for immutable historical documents, audit evidence, and current-state summaries. Those exceptions require consistency rules rather than an unsupported claim of strict normalization.

## Structures that support normalization

- Users, offices, roles, permissions, service types, and categories have separate records.
- `OfficeMembership`, `UserRole`, `RolePermission`, and `PersonnelEligibility` use composite keys for many-to-many relationships.
- Assignments, status events, clarification messages, progress notes, and revisions are separate rows rather than repeated columns on a request.
- `ServiceEvaluation.request_id` is its primary/foreign key. The eligible evaluator is derived from the request, avoiding an independent duplicate requester field.
- General feedback and verified service evaluations represent distinct kinds of submissions.

## Outstanding refinements

### Priority matrix version metadata

The current `PriorityRule` proposal includes a matrix version, urgency, impact, resulting priority, and effective dates, with a candidate key of `(rule_version, urgency, impact)`.

If every entry in one matrix version shares the same effective dates, then `rule_version` determines those dates. This is a partial dependency on a composite candidate key, violating 2NF and therefore 3NF even though the table also has a surrogate primary key.

Under that business rule, use:

- `PriorityRuleVersion(id, version_number UNIQUE, effective_from, effective_until)` for version metadata.
- `PriorityRule(id, priority_rule_version_id FK, urgency, impact, resulting_priority)` for matrix entries, with `UNIQUE(priority_rule_version_id, urgency, impact)`.

Requests continue referencing the exact immutable rule entry applied. If effective dates instead belong independently to each rule entry, confirm that meaning before applying this split. The architecture does not settle that detail, and the refinement has not yet been applied to the ERD.

### Individually managed authorization scopes

The current proposal stores `AgentAuthorization.allowed_tool_scopes` as JSON. If scopes are individually assigned, queried, and validated as permission memberships, use a `ToolScope` table and an `AgentAuthorizationScope(agent_authorization_id, tool_scope_id)` junction with a composite primary key and foreign keys. Reuse the chosen authentication framework's equivalent schema where available.

This is a recommended relational refinement, not a claim that every JSON column violates 1NF. It has not yet been applied to the ERD.

### Audit identity and operation facts

For MCP events, an authorization identifies its user, and an operation identifies its authorization and tool. Repeating those references in `AuditEntry` creates redundant facts.

Either derive the repeated facts through the relationships or explicitly retain them as deliberate, immutable audit evidence with enforced consistency. If retained, document the exception and do not claim that portion of the model is strictly normalized without further dependency analysis. Ordinary API, read-tool, anonymous, and system events must remain representable.

For agent-originated reports, `ReportJob.requested_by` may also repeat a user available through the operation. One requester field across both ordinary API and MCP report requests is a defensible design choice; enforce agreement whenever an originating operation exists. Such cross-table duplication does not automatically prove a local 3NF violation.

## Intentional history and current-state storage

- Request status and completion timestamps summarize operational events recorded in history. Keeping these current values is practical, provided related changes and audit entries commit transactionally. Cross-table duplication alone does not establish a formal 3NF violation of `ServiceRequest`.
- Revision snapshots and audit before/after documents preserve facts at a specific time. They are not replacements for normalized live relationships.
- Submission contact details can remain snapshots if they mean the contact details supplied for that submission. If they instead mean the user's current profile values, derive them from `User`.
- A request's official priority is an authorized decision and may differ from the rule's normal output through a documented exception. Do not treat it as an unconditional copy of the rule result.
- Status, classification, and other controlled values do not require separate lookup tables merely to satisfy 3NF. Choose lookup tables when the values need independent metadata or management.

## Conditions for final validation

Before claiming the implemented schema satisfies its normalization target:

1. Finalize each attribute's meaning, candidate keys, and functional dependencies.
2. Resolve priority-version dates and authorization-scope storage.
3. Record intentional duplication and define which records are authoritative for current state and historical evidence.
4. Create migrations with the required primary keys, foreign keys, unique constraints, nullability, and domain constraints.
5. Validate insert, update, and deletion behavior for unintended anomalies. Test the workflow, ownership, office scope, and concurrency rules separately from normalization.

No implemented database has been certified by this assessment. The [Database Design acceptance scenarios](database-design.md#verification-scenarios-before-implementation-acceptance) describe further verification required before implementation acceptance.

## Implemented MCP refinement · 23 October 2026

The [read-only pilot](mcp-implementation.md#authorization-storage-and-audit-consistency) reuses Sanctum credential storage and adds a tool catalog plus a composite-key authorization/scope junction. JSON token abilities are empty; relational memberships grant tools. Invocation audit identity is derived through the authorization, avoiding independent actor/client copies. The authorization/token user bridge intentionally overlaps the framework's polymorphic owner and is checked for equality during authentication; issuance is transactional. Revocation retains identity rows for audit provenance. These physical refinements resolve the affected scope/audit proposals for this read-only milestone; operation/outcome and approval tables await operational writes. Existing physical `priority_rule_versions` already separates version-wide effective dates. The proposed ERD and complete schema remain uncertified.

## Implemented category and report refinement · 20 October 2026

The physical prototype now stores category names once in `request_categories`, with `id` as primary key and `(handling_office_id, name)` as a unique office-local name. Requests reference category identity; names are derived on reads. Independent service types do not determine categories. The composite office/category foreign key enforces scope and does not make the requesting office equivalent to the handling office.

Report aggregates are calculated from operational rows in one transaction at generation time. Stored PDF/Excel/CSV files are intentional immutable historical documents, not operational summary tables to synchronize. A report job retains the authorizing office filter and requester for later authorization checks. Category creation audit snapshots and request before/after snapshots deliberately preserve historical evidence. This extension does not resolve all outstanding proposal dependencies or certify the full schema as 3NF; the prototype's priority-version and restricted operational access choices remain documented in [implementation notes](../project/implementation-notes.md).
