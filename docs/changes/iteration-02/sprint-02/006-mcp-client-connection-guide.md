# MCP client connection guide

23 October 2026, Asia/Manila. Iteration 02 / Sprint 02, following the user's request for a how-to guide for Claude, ChatGPT and Gemini.

## Change

Added the canonical [client connection guide](../../../project/mcp-client-connection-guide.md), with a compatibility table, repository-specific server preparation, separate scoped credentials, Claude web/Desktop and local Claude Code paths, ChatGPT OAuth prerequisites, Gemini Apps eligibility/authentication limits, local Gemini CLI settings, verification prompts, troubleshooting and revocation. Updated the documentation entry points and preserved the [foundation implementation record](005-mcp-read-foundation.md).

Reviewed official Anthropic, OpenAI and Google documentation on this date. Claude request-header access is a limited-rollout feature; ChatGPT's authenticated custom-MCP connection requires the unimplemented OAuth flow. Google's consumer Gemini custom-app requirements currently exclude Philippine locations and work/school accounts. The guide does not present those routes as verified integrations or confuse consumer apps with CLI/API products.

No application code, dependencies, database rows, runtime settings, client-account configurations or provider connections were changed. No pilot credentials were issued and no deployment or tunnel was started. All credential/configuration examples use placeholders or an environment reference. The documentation does not authorize exposing real campus data to providers.

## Verification

| Check | Result |
| --- | --- |
| `python scripts/check-docs.py` | PASS — 66 Markdown files and 352 local links/anchors. |
| PowerShell examples | PASS — all 8 fenced examples parsed with the PowerShell language parser; no example was executed. |
| JSON example | PASS — parsed with `ConvertFrom-Json`; contains an environment-variable reference rather than a credential. |
| `git diff --check` | PASS. |
| Source review | Official vendor pages were opened and compared with the actual server endpoint, protocols, credential command and middleware. |

Neither `claude` nor `gemini` was found by `Get-Command` in this session. No CLI/vendor-account interoperability test was performed. Application tests were not repeated for this documentation-only change; prior implementation results remain in record 005 and are not new client connection evidence.

[Sprint overview](sprint-overview.md) · [Connection guide](../../../project/mcp-client-connection-guide.md)
