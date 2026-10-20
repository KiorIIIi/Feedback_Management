# Verification · 20 October 2026

## Temporary device-hosted public synthetic demo

The `hosted-demo` TypeScript/Vite build, PHP syntax checks for all four public-host PHP scripts, PowerShell syntax checks for the installer/start/stop scripts, and the 13 non-destructive local-runtime checks passed. The Cloudflare Windows client download was verified against the official release SHA-256. Public startup and stop/restart were exercised; a separate synthetic MySQL database and scoped runtime account were used. No existing local-demo data was copied or reset.

The dedicated public-host browser check passed through the generated HTTPS URL using installed Chrome. It verified frontend assets and SPA headers; private-file and unauthenticated API denial; requester UI login, Secure/HttpOnly session cookies, refresh/session restoration; administrator/personnel login; cross-owner record denial and internal-note filtering; a labeled synthetic request through assignment, staff progress/completion, stale-version rejection, and one verified evaluation; queued CSV completion and private download authorization; rejection of the README's local-demo password; and authenticated routes/mobile reflow without JavaScript errors. Evidence and the current temporary URL are in `.local/public-host/verification.json`; credentials are stored separately and are not part of that evidence.

The connector initially failed DNS discovery on this PC. Resolving official edge addresses using Windows and passing the explicit-edge option established the TLS tunnel; the launcher allows time for the new hostname to propagate and checks the public HTTPS health endpoint before success. These checks ran from the host PC through Cloudflare, not from a separately operated external device. They do not establish uptime, production load capacity, backup restoration, final MISO policy approval, or readiness for real campus data.

## Latest supplied security audit follow-up

The findings in `security-audit.md` were reconciled in [the audit follow-up](security-audit-follow-up.md). **45 backend tests / 272 assertions passed on SQLite and on the isolated MySQL test database.** Six new regressions cover fail-closed non-development configuration, CORS, trusted/untrusted proxies with HTTPS/HSTS, account-isolated read budgets, hashed login limits/cooldown recovery, and transactional notification rollback. CSV round-trip coverage now includes BOM, zero-width space, RTL override, combining prefixes, and safe strings.

**All 31 isolated browser tests passed**, including the new default-off Remember me behavior, explicit opt-in, remembered-session restoration, and logout. The frontend production build passed. Demo request/report counts and remembered-login tokens were unchanged, and no QA listeners remained. The generated CSV was removed from the Git index as a staged deletion and testing storage was added to ignore rules; history and unrelated staged work were preserved.

Production startup and logging default/override probes passed. A fixed single-origin CORS header was initially mistaken for a failed allowlist assertion; inspection and tests confirmed the mismatching header does not grant an unlisted origin access. The account-wide login-limit availability tradeoff, actual proxy/static hosting/TLS validation, final recaller lifetime, and full history/spreadsheet/load tests remain outside the completed local fixes. The original supplied audit was not rewritten.

## Final Gemini report reconciliation

The supplied final report was reconciled in [security review](security-review.md). Backend regressions passed **39 tests / 183 assertions on SQLite and separately on MySQL**. The frontend build passed, and **all 31 browser tests passed in one run** using the new isolated Playwright frontend/API/queue/database. Demo request/report counts and remembered-login tokens were unchanged; QA processes released ports 5174/8001 afterward. The new runner uses per-run SQLite integration storage; the separate PHPUnit MySQL suite retains database-specific coverage.

Explicit model field lists now exclude authority/identity/workflow fields from ordinary mass assignment. Laravel and Vite supply the configured response headers; production Laravel adds CSP and HTTPS-only HSTS. Session configuration probes verified secure production defaults and compatible local defaults. Failed-login tests confirmed Laravel's existing delay across absent-email, incorrect-password, and wrong-role paths; a redundant dummy hash was not added. Account-linked signed-in feedback remains explicitly disclosed rather than silently converted to an anonymous mode. Deployment headers on a production static web server remain an environment-specific requirement.

## Gemini security candidate follow-up

The [security review](security-review.md) records the disposition of the supplied audit plan's findings. At that stage backend runs passed **34 tests / 153 assertions on SQLite and on isolated MySQL**, plus an unsafe-target CLI probe that refused the demo database before migrations. Ten real-service browser checks and the frontend build passed. Locked Composer and frontend npm audits returned zero reported advisories/vulnerabilities at review time. Historical-note and frontend secret checks returned zero matches within their documented, limited scope. The review also records a test-export storage incident and its repair/quarantine. Gemini's final artifact was pending at that stage; the follow-up above supersedes its remaining E2E isolation limitation.

## Bug audit and regression fixes

The [bug audit](bug-audit.md) records 24 corrected findings across privacy, authorization, authentication recovery, request filters/forms, report polling, notifications, timezone display, test cache isolation, and startup checks.

- Final backend suite: **20 tests / 87 assertions passed on SQLite**, and **20 tests / 87 assertions passed on MySQL 8.0.30** using only `clsu_feedback_test`. Ten new bug-reproduction/compatibility tests plus a cache-isolation regression augment the existing nine lifecycle/dashboard tests.
- Final TypeScript/Vite production build passed after all frontend changes.
- **31 distinct browser tests passed across staged runs**: 13 API-fixture regressions, four responsive tests, three role tests, three workflow/export/asset tests, one requester dashboard test, four session tests, and three shared staff-login tests. The final seven session/staff checks passed after the cache-isolation correction. Authentication checks were spaced to respect the existing login limit.
- Thirteen non-destructive launcher checks passed. The current launchers started/reused the project-owned MySQL/API/frontend/worker successfully. The launcher tests also passed under Windows PowerShell 5.1 during the initial audit.
- Independent code review identified the saved-update/detail-refetch recovery issue; it was corrected and covered by an additional regression. Current requester mobile and admin compact-desktop captures were visually inspected; the supplied composition and asset usage remain intact.

Intermediate failures were investigated: stopped local services caused connection-refused results after resumption; a timestamp assertion assumed a different locale ordering; concurrent browser runs collided with output files and login throttling. The cache investigation uncovered an actual isolation defect: backend tests ignored CACHE_STORE=array and shared the live file-cache limiter. Cache configuration now honors the environment and has a regression test. The app's login limit was not relaxed.

No schema migration or reset of the demo database was performed. Browser workflow tests add synthetic requests and reports. No production-readiness or exhaustive security/accessibility certification is implied.

## Responsive and zoom-equivalent layout follow-up

Four Playwright reflow tests passed across public welcome/login/feedback and requester, personnel and admin pages. Sixteen routes were checked at ten effective CSS viewport sizes from 320 to 2400px, including layouts equivalent to a 1920px display at 80%, 100%, 125%, 150%, 200% and 250% browser zoom. Checked page-level overflow, image loading, welcome-panel centering, artwork containment, and compact navigation/sidebar toggles. These tests exercise the CSS viewport produced by zoom; they do not manipulate Chrome's toolbar zoom control.

The production TypeScript/Vite build passed. Nine screenshots named `reflow-*.png` are in `.impeccable/review/`. Independent visual review marked the scoped responsive adaptation ship. Browser zoom remains enabled; fonts are not counter-scaled. Large data tables retain their internal horizontal scrolling.

## Selected requester dashboard follow-up

Matched the user-confirmed middle Figma `Your dashboard` frame (`26:22477`). Production build passed. Four browser tests passed for dashboard data/actions/local assets, desktop/mobile overflow and the existing session behavior. The new requester-dashboard count/ownership test passed against both SQLite and MySQL: one test, nine assertions on each. The independent visual review marked the scoped dashboard ship. Final captures: `.impeccable/review/requester-match-1440.png` and `requester-match-429.png`. Counts and job references remain live data rather than copied sample values.

## Role routing and session follow-up

The latest production build passed. Six Chrome tests passed for role-specific admin/personnel/requester landing pages, refresh and remembered sessions, temporary restoration failure/retry, selected-role credential rejection, account switching, and personnel navigation restrictions. Admin/personnel desktop and mobile screenshots are in `role-*-desktop.png` and `role-*-mobile.png`; role tests assert mobile document overflow is absent. The visual reviewer marked the scoped role-workspace change ship. Personnel work UI adapts existing components because no separate personnel work-dashboard Figma frame was supplied.

## Initial implementation checks

- Frontend TypeScript and Vite production build passed. Application and chart bundles are split; fonts and Figma assets are local.
- Backend feature suite passed on SQLite: 8 tests, 45 assertions before the final export-scope regression assertion was added.
- Final backend suite passed on isolated MySQL 8.0.30: 8 tests, 46 assertions. Covered complete lifecycle, clarification identity, evaluation uniqueness/ownership, office boundaries, internal-note privacy, stale-version rejection, rollback on invalid transitions, personnel restrictions, general feedback separation, private exports, changed-office export denial, and cross-origin write rejection.
- Final browser suite passed: 3 tests covering desktop/mobile captures and loaded assets, persisted request submission/search/filtering, and queued CSV download through the real local report worker.
- All 262 exported Figma asset files are present and non-empty. Visible image loading and horizontal page overflow were checked at desktop/mobile sizes.
- PHP syntax checks passed for 25 application/configuration/database/route files; startup PowerShell script parsed without errors. Design sidecar JSON parsed successfully.
- Independent visual reviewer marked the two reported corrections resolved: reporting-period label and populated-service-table screenshot. Disposition: ship within rough-build scope. This is not pixel-equivalence certification across all Figma frames.

Desktop/mobile evidence is in `.impeccable/review/`. Browser tests add synthetic requests and reports to the local demo database. Backend tests use a separate database and never reset the demo data.

No production deployment, real-user data, email delivery, MCP execution, load test, simultaneous-write stress test, full screen-reader audit, or backup restoration was performed. Draft configuration and deferred functionality are documented in `implementation-notes.md`.
