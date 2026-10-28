# MCP adapter boundary

The opt-in read-only pilot is implemented with Laravel MCP and Sanctum. `config/mcp.php` keeps it disabled by default. The embedded assistant remains disconnected; no provider or chat runtime is configured.

The canonical [implementation guide](../../../../docs/architecture/mcp-implementation.md) documents setup, the five read tools, relational scopes, credential issuance/revocation and invocation auditing. All new integration documentation belongs under `docs/`.

Before exposing operational writes, implement caller/tool-scoped operation IDs with input fingerprints and persisted outcomes, atomic operation/domain/history/audit commits, permission-checked result retrieval and bound approvals where configured. Resolve identity from server authentication and share `RequestWorkflow` rules. Never allow arbitrary SQL/shell or MCP account/configuration administration.

The full catalog in [system architecture](../../../../docs/architecture/system-architecture.md#mcp-connection-and-agent-capabilities) remains the plan. No operational write, report creation or completion tool is registered. Completion remains human-only, even when future handlers delegate to `RequestWorkflow`.
