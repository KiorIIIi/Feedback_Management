# US-012 — MCP integration, first milestone

Iteration 03 / Sprint 03 — **Implementation of major feature**. Retrospective implementation record for work delivered on 23 October 2026; the user assigned this grouping on the same date.

As an authorized MISO administrator, I can connect an approved MCP client to read permitted service information through the same server authorization rules used by the portal.

## Delivered scope

- Use the installed Laravel/PHP stack with compatible pinned official MCP and Sanctum packages.
- Expose an opt-in stateless HTTP MCP endpoint with five read tools: request search/details, personnel workload, verified-feedback metrics and report-job status.
- Derive identity from expiring/revocable credentials; enforce administrator role, fixed handling office, individual tool scopes and record access.
- Store scope memberships relationally and derive invocation user/client provenance through the authorization. Do not audit secrets or record payloads.
- Reuse the portal's authorization/read services; bound pagination, minimize response fields and identify untrusted record text.
- Preserve report ownership/private-file checks and distinguish pending/processing/failed reports from ready exports.
- Provide client-specific setup instructions that distinguish currently compatible token-based paths from OAuth, feature-access and region/account prerequisites.

## Implementation and acceptance

The [canonical implementation guide](../../../../../architecture/mcp-implementation.md) describes the physical schema, stack, security checks and deferred release boundaries. The [client connection guide](../../../../../project/mcp-client-connection-guide.md) contains the how-to instructions. Keep those topics canonical rather than duplicating them in sprint records.

Acceptance for this milestone covers authentication, tool/office/record restrictions, credential expiry/revocation, relational integrity, safe audits, bounded read results, report status privacy and continued portal workflows. The [story verification](../sprint-03-tests-US-012.md) links actual evidence.

Operational writes require a later milestone with caller/tool-scoped operation identifiers, input fingerprints, atomic outcomes/history/audits, stale-version checks and bound approvals where configured. OAuth, actual vendor interoperability and embedded AI remain unfinished. Completion stays human-only; no arbitrary SQL/shell or account/configuration tools are included.
