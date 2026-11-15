# Agent API keys

14 November 2026. Administrators can generate and revoke scoped MCP bearer keys in **MCP Assistant → Connection settings → Agent API keys**. Enable MCP first, select read tools, name the agent, choose a lifetime of 1–168 hours, and confirm the current password. Copy the key immediately; it is shown once and stored hashed through Sanctum.

Configure a compatible HTTP MCP client with the displayed server URL and `Authorization: Bearer YOUR_API_KEY`. Remote clients require a reachable HTTPS endpoint. Clients without custom bearer-header support must use the existing OAuth flow where supported. Creating a key does not establish a verified external connection.

Keys retain administrator identity, office restrictions, explicit tool scopes, expiry, invocation auditing and revocation. Management requires the hosting operator's admin-setup flag. Creation and revocation audit only identifiers and scopes, never raw secrets. This adds no operational write tools and does not enable outbound provider calls. External agents receive the authorized records they request.

No schema changes are required. Verification results are reported with the implementation handoff.
