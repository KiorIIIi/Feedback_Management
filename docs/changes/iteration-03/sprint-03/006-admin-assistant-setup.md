# Admin assistant setup and ChatGPT OAuth

25 October 2026, Asia/Manila. The administrator identified the reserved MCP screen as missing a required admin setup feature, then requested both external ChatGPT and an embedded assistant.

Replaced the placeholder with an admin assistant workspace and connection settings. Added password-confirmed, version-checked configuration; encrypted server-side model/key storage; external MCP enablement; per-administrator ChatGPT client registration, individual read-tool selection, and revocation. The header now reflects server readiness instead of a hardcoded disconnected label. Detailed behavior and deployment prerequisites are in the [admin setup guide](../../../project/assistant-admin-setup.md).

External ChatGPT now has an OAuth authorization-code/S256-PKCE implementation using Passport 13.10.0 and existing MCP read services. Exact callback validation, consent, token/resource/client/office binding, expiry, refresh and revocation are server boundaries. No public deployment, tunnel or actual ChatGPT account connection is claimed.

The requested embedded model transport was rejected by automatic approval review because sending potentially private messages and tool results to `api.openai.com` lacked explicit data/destination authorization. A specific approval question is pending. The model configuration and conversation interface are present, but server validation and UI prevent provider execution. No model-provider request ran.

## Verification

- Initial SQLite admin/OAuth checks passed: **3 tests, 43 assertions**. Existing MCP regressions passed during the earlier combined run; follow-up full results are recorded after completion.
- Initial frontend TypeScript/Vite production build passed after correcting two TypeScript issues.
- The additive migration applied to the verified local `clsu_feedback` database on loopback port 3307. Existing counts stayed **12 requests, 0 general feedback entries, 12 notifications**. No reset or seed ran.
- MySQL, browser, final regression and documentation results are pending in this working record; do not treat configured tests as passing evidence.

Initial verification caught a Windows OpenSSL configuration failure, a Passport/Sanctum model-trait conflict and a browser assertion that read the header before the initial signing-key save completed. Corrections use Passport's key generator with PHP's bundled configuration, a separate Passport user provider mapped back to the domain user, and an explicit save-response wait.

[Sprint overview](sprint-overview.md) · [Iteration overview](../iteration-overview.md)
