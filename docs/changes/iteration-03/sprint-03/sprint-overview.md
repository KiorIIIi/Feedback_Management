# Iteration 03 / Sprint 03 — Implementation of major feature

User-designated grouping: **23 October 2026, Asia/Manila**. Major feature: authenticated MCP integration, beginning with the read-only pilot and external-client connection documentation.

| Record | Scope |
| --- | --- |
| [024 — GitHub Actions test fixes](024-github-actions-test-fixes.md) | MySQL JSON audit assertion and browser sign-out navigation synchronization |
| [023 — PHP, Composer and Laravel upgrade](023-php-composer-laravel-upgrade.md) | Laragon PHP 8.6, Composer 2.11 and the backend upgrade to Laravel 13 |
| [022 — System audit remediation](022-system-audit-remediation.md) | Audit findings A1, A4–A10, A13–A15 fixed with regression tests; six dependency advisories resolved; A11 deferred |
| [021 — Browser-suite remediation for stale specs and the assistant key-list budget](021-e2e-suite-remediation.md) | Playwright suite red at HEAD: roles/assistant specs updated to the recorded 017 and 010/014 behavior, portal sign-out viewport fix, and assistant key listing moved out of the 6/min write limiter with a regression test |
| [020 — Shareable feedback form link with event QR](020-shareable-feedback-form-link.md) | Admin share panel: public form link, copy button, client-side QR (PNG/SVG), event poster printing, and optional `?event=` collection label stored with general feedback |
| [019 — Backend error audit remediation](019-backend-error-audit-remediation.md) | Audit findings M01–M06 and L01–L12 fixed; policy dead-end documented, tests added |
| [018 — Personnel workspace finish](018-personnel-workspace-finish.md) | Personnel dashboard and mobile visual consistency |
| [017 — Shared staff login](017-shared-staff-login.md) | Shared staff sign-in and legacy link handling |
| [016 — Notification return navigation](016-notification-return-navigation.md) | Back navigation to the originating page with role-dashboard fallback |
| [015 — Settings and status colors](015-settings-status-colors.md) | MCP settings location, feedback accents and clearer indicators |
| [014 — Dashboard and navigation](014-dashboard-navigation.md) | Combined control/date column, sidebar sign-out and relocated MCP status |
| [013 — Public pages and sidebar](013-public-pages-sidebar.md) | Welcome/login styling and restored collapsed icon rail |
| [012 — Shared workspace finish](012-shared-workspace-finish.md) | Requester/personnel styling, softer corners and green interaction accents |
| [011 — Admin visual layer](011-admin-visual-layer.md) | Warm neutrals, compact tables, humanist typography and mobile refinements |
| [010 — Assistant and sidebar](010-assistant-sidebar.md) | Unified MCP page and desktop sidebar collapse |
| [009 — Combined service workspace](009-service-workspace.md) | Requests and Personnel tabs, legacy redirect and assignment navigation |
| [008 — Admin workspace refinement](008-admin-workspace-refinement.md) | Shared admin density, request-first dashboard and connection settings hierarchy |
| [007 — Agent API keys](007-agent-api-keys.md) | Password-confirmed generation, scoped bearer access and revocation for compatible agents |
| [001 — MCP read foundation](001-mcp-read-foundation.md) | Official Laravel MCP/Sanctum packages, five read tools, scoped credentials, shared services, auditing and original SQLite/MySQL evidence |
| [002 — MCP client connection guide](002-mcp-client-connection-guide.md) | Claude, ChatGPT and Gemini setup paths, OAuth/account prerequisites, troubleshooting and original documentation checks |
| [003 — Iteration and sprint classification](003-iteration-and-sprint-classification.md) | User-requested Iteration 03 / Sprint 03 grouping, preserved record history, updated navigation and documentation verification |
| [004 — Security audit remediation](004-security-audit-remediation.md) | CI repair, strict MCP enablement, private demo secret files, provisioning cleanup and interrupted-invocation recovery |
| [005 — Live notifications](005-live-notifications.md) | Scoped submission alerts, unread badges, automatic updates and preservation of editing drafts |
| [006 — Admin assistant setup](006-admin-assistant-setup.md) | Admin settings, ChatGPT OAuth, read-tool client management and an explicitly authorized local-only demo assistant |

Status: the first implementation milestone is verified against the recorded backend checks. The guide is documentation-verified only. US-012 remains partially implemented: write retries/approvals, operational tools, OAuth, embedded AI and actual external-client verification are deferred. Sprint documentation does not establish production or MISO acceptance.

The [25 October setup follow-up](006-admin-assistant-setup.md) implements admin configuration, OAuth and local synthetic read tools; earlier dated verification and release boundaries remain historical evidence. Actual ChatGPT-account and live-provider interoperability remain unverified without the required account/key setup.

Add subsequent work in this sprint as the next sequential change file; preserve the dated evidence in earlier records.

[Iteration overview](../iteration-overview.md) · [All iterations](../../README.md) · [Agile sprint record](../../../agile/sprints/EPIC-003/sprint-03/sprint-03.md)
