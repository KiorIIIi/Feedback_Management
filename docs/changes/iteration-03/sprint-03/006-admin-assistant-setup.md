# Admin assistant setup, local demo tools and ChatGPT OAuth

25 October 2026, Asia/Manila. Replaced the reserved MCP screen with an admin workspace and setup interface. The user's latest instruction is to keep data local and leave provider calls disabled. This supersedes earlier synthetic-provider approval; the outbound model transport was removed before delivery. No live model request was made.

Administrators can save password-confirmed, version-checked configuration, enable/disable the MCP server, register their own ChatGPT OAuth clients, select read tools and revoke access. Existing model/key configuration is encrypted on the server; provider fields and calls remain disabled under the current policy. The header reflects actual server readiness without claiming a connected client. The [admin setup guide](../../../project/assistant-admin-setup.md) documents behavior and deployment prerequisites.

The Assistant tab provides local synthetic demo diagnostics through the existing five authorized read services. Reads validate arguments and office/record scope, record minimal invocation outcomes and revoke their temporary credentials. These checks have no provider transport and are excluded from external-client activity indicators. Provider chat/test endpoints reject calls even if database settings are manually changed.

External ChatGPT has an OAuth authorization-code/S256-PKCE path using Passport 13.10.0: exact callbacks, explicit human consent, fixed resource/client/user/office binding, selected tools, expiry, refresh, code-replay rejection and revocation. No public deployment, tunnel, actual ChatGPT account connection or real-campus-data use is claimed. The local MCP server remains disabled until an administrator explicitly enables it.

## Verification

- Initial SQLite and isolated MySQL admin/OAuth checks passed: 3 tests / 43 assertions on each. Later SQLite renewal/replay checks passed: 3 tests / 51 assertions.
- Before the final local-only choice, the full SQLite suite passed 102 tests / 782 assertions with one expected optional MySQL-provisioning skip, and all 42 browser tests passed. Mocked synthetic-provider code from that intermediate state was subsequently removed; these counts do not verify the final local-only variant.
- Revised local-only setup/diagnostic checks passed on both SQLite and isolated MySQL: **6 tests / 70 assertions on each**, including forced provider denial and no outbound HTTP requests. All three browser cases passed across scoped runs after adapting the navigation to the local-tools screen. Final CI results are recorded separately.
- The additive migration applied to the verified local database on loopback port 3307. Counts stayed **12 requests, 0 general feedback entries, 12 notifications**; no reset/seed ran.
- The production TypeScript/Vite build passed after the local-only revision. Composer reported no advisories. Thirteen non-destructive runtime checks, six private-file ACL checks and syntax checks for updated public PHP scripts passed.
- Documentation link checks passed: **80 files / 456 links and anchors**. Whitespace checks passed. A live local smoke check confirmed rendered local results, provider calls disabled, MCP disabled, HTTP 409 on provider chat, a working notification feed and no JavaScript errors.

Verification corrected Windows OpenSSL configuration, Passport/Sanctum model-trait incompatibility, a post-save reload race, an early stylesheet layer-order regression and a truthy cast in the legacy enablement fallback. A host pause interrupted one browser-startup attempt; it was not counted as passed. Local smoke checks use rendered controls rather than network-idle on polling pages. Provider-disabled state and local diagnostics are verified separately from external-client interoperability.

## Final remote verification

[GitHub Actions run 40172377551](https://github.com/KiorIIIi/Feedback_Management/actions/runs/40172377551) completed successfully for code commit `b8ec352`. All six jobs passed: backend MySQL, backend SQLite, browser, frontend, documentation and Windows runtime/ACL checks.

- MySQL: **100 tests / 790 assertions, no skips**.
- Linux SQLite: **100 tests / 775 assertions, one expected optional MySQL-provisioning skip**.
- Chrome: **all 42 tests passed in one run** on the final local-only variant, including live notifications and all three admin/OAuth/local-tool tests.
- Locked dependency checks, frontend build, documentation links and Windows helper/private-file checks passed.

No application code changed after this verified commit. Subsequent evidence-only documentation updates do not enable a provider, publish the system or establish external-client interoperability.

[Sprint overview](sprint-overview.md) · [Iteration overview](../iteration-overview.md)
