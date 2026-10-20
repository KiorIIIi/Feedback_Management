# Future MCP adapter boundary

No MCP server, client, provider, credential, or chat execution is installed or enabled in this prototype. `config/workflow.php` marks MCP disabled. The admin navigation preserves the Figma assistant slot and displays an honest disconnected state.

Implement a future authenticated transport here. Resolve caller identity from authentication, then invoke `RequestAccess`, `RequestWorkflow`, and `ReportingService`; never duplicate rules in tool handlers or allow arbitrary SQL.

Before exposing tools, implement revocable authorization and relational tool scopes, caller/tool-scoped operation IDs with input fingerprints and persisted outcomes, read/write invocation audit provenance, and bound approvals where configured. These are not implemented by the disabled placeholder. Persist operation outcomes in the same transaction as the domain change. Enforce expected record versions and office scope through the existing services.

Start with the catalog in `docs/architecture/system-architecture.md`. Do not expose completion through MCP: only authenticated human API callers may currently complete services. A future transport must reject agent completion even if it internally delegates to `RequestWorkflow`.
