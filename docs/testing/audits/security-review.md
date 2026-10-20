# Gemini candidate review — 20 October 2026

The subsequent supplied [security audit](security-audit.md) is reconciled separately in [the latest audit follow-up](security-audit-follow-up.md). That follow-up supersedes the older remembered-login default and records production guards, CORS/proxy configuration, logging, Unicode CSV coverage, and remaining deployment decisions.

Initial input: Gemini's `implementation_plan.md` in artifact directory `d0dc52ab-c733-47e0-b058-3cd295c79cd8`. The final report was subsequently supplied as attachment `0a98d355-4d3a-41ee-8f65-6bf101cbfbff/Pasted text.txt`. Both were reconciled against the installed framework, application code, and isolated regression tests.

## Final report reconciliation

| Final recommendation | Disposition |
| --- | --- |
| Automatically anonymize public feedback | The current form explicitly states that signed-in submissions are account-linked, even with blank contact fields. Guest submissions remain unlinked. There is no promised signed-in anonymous mode; adding one requires an explicit product/privacy decision rather than inferring anonymity from optional contact fields. |
| Replace guarded models with fillable lists | Implemented more strictly than the suggested lists: role, activation, office authority, request ownership, job number, status, priority, and version fields are excluded from ordinary mass assignment. Trusted workflow creation and synthetic seeding explicitly assign their protected fields. Regression tests attempt protected-field injection. Merely listing role/status as fillable would not protect them. |
| Add dummy bcrypt checks for allegedly instant login failures | No duplicate password calculation added. The installed Laravel Timebox wraps the complete failed attempt. Regression checks confirm missing-email, wrong-password and wrong-role failures retain the minimum delay. Production timing under different hash costs/load remains outside this scoped test. |
| Add response security headers | Added nosniff, frame denial and referrer policy to Laravel responses and Vite HTML responses. Production Laravel responses additionally receive CSP, and HTTPS responses receive HSTS. A production server serving the static SPA must apply the equivalent header policy to that HTML; this is not a deployed reverse-proxy configuration. |
| Enforce production session security configuration | SESSION_ENCRYPT and SESSION_SECURE_COOKIE are configurable and default to true in production. Local HTTP defaults remain compatible with existing sessions. Separate configuration probes verify production/local defaults and explicit overrides. |
| Isolate Playwright execution | Implemented: Playwright starts its own frontend/API/queue with a new file-backed SQLite database per run. Cookies, sessions, private reports, cache, compiled views and Laravel cache manifests are isolated. Screenshots go into Playwright test output rather than shared review images. The ordinary demo server/database is not the browser-test target. |

The isolated E2E stack uses ports 5174/8001 and refuses occupied ports; it does not reuse or stop another service. Artifacts stay under `.local/e2e/<run-id>/`, and the processes started by Playwright are stopped on completion. The application itself remains Laravel/MySQL; MySQL-specific feature checks continue separately against the allowlisted `clsu_feedback_test` database. A test-only login budget supports a full browser run without changing local/production limits; limiter behavior is covered separately by feature tests.

## Confirmed issues corrected

| Finding | Resulting behavior | Main implementation |
| --- | --- | --- |
| Login and feedback shared an unnamed limiter; there was no account-wide login limit. | Separate named buckets; login is limited by IP and normalized, hashed email across IPs. Limits are configurable local safeguards. | `AppServiceProvider.php`, `routes/web.php`, `config/security.php` |
| Personnel could resume a pending request after clarification without administrator review. | Personnel cannot manage Pending or For Clarification records. An administrator must resume review/assignment; retained assignment history alone cannot grant review authority. | `RequestWorkflow.php` |
| Assigned personnel received private evaluation remarks through request APIs. | Personnel receive `evaluation: null`; owners and authorized office administrators retain their permitted access. | `RequestAccess.php` |
| A non-MISO admin could commit a MISO request and then receive 404 when presenting it. | Unsupported staff-office creation is rejected before insertion. Administrator scope is not broadened to other offices. | `RequestWorkflow.php` |
| Missing Origin bypassed the custom check, and arbitrary request Host values became trusted origins. | Writes require an explicitly trusted Origin, or a trusted Referer when Origin is absent. Host is not a trust source. JSON-only and cross-site rejection remain enforced. | `VerifyPortalOrigin.php` |
| Internal/no-op updates generated requester notices; requester replies notified the requester. | Internal-only updates do not notify the requester or establish a public first response. No-op management is rejected without version/history changes. Clarification replies notify active handling-office administrators; new assignments notify the assignee. | `RequestWorkflow.php` |
| Explicit `personnel_id: null` was ignored. | Administrators can clear an assignment during review. Active-work statuses still require an assignment, and invalid unassignment rolls back. | `RequestWorkflow.php` |
| Reports had no submission limiter or active-job cap. | Five report submissions/minute and two pending/processing reports per account by default. A locked user row serializes the cap check. Other mutation endpoints have an account-scoped rate limit. | `ReportController.php`, named route limiters |
| CSV escaping missed whitespace/newline-prefixed formula content. | All CSV cells are checked for leading formula/control content, including whitespace prefixes; risky cells receive an apostrophe. Pipe-prefixed cells are conservatively protected too. | `GenerateReport.php` |
| Missing completed-report files produced server errors. | Authorized downloads return a controlled 404 with instructions to request a new export. | `ReportController.php` |
| Inherited database settings could direct RefreshDatabase at a live database. | Before migration traits run, tests reject cached configuration, non-testing environments, file-backed SQLite, and non-allowlisted MySQL targets. Allowed targets are SQLite `:memory:` or local `clsu_feedback_test` on port 3307. Critical test runtime settings are forced in PHPUnit configuration. | `tests/TestCase.php`, `TestEnvironmentGuard.php`, `phpunit.xml` |
| Signed-in public feedback linkage was not explicit in the form. | The existing notice now explains that feedback is linked to the signed-in account even when optional contact details are blank. No anonymous mode was invented. | `pages.tsx` |

Default guards are in `backend/config/security.php`. Additional browser origins must be explicitly configured through `PORTAL_TRUSTED_ORIGINS`; the normal frontend uses APP_URL. Cookie-authenticated scripts must supply a trusted Origin or Referer. Future MCP authentication remains outside this browser session flow.

## Candidates not established as vulnerabilities

- **Immediate login timing/role enumeration:** the installed Laravel SessionGuard wraps failed authentication in a Timebox; AuthManager supplies a default 200,000-microsecond duration. The plan's asserted immediate-return behavior does not describe the complete installed authentication path. No production timing benchmark or exploitable timing channel was demonstrated. This is a mitigation, not a constant-time guarantee under every password cost and server load.
- **LIKE wildcards:** search values remain bound parameters and results remain server-scoped. Wildcard matching can broaden a user's own search; it does not establish SQL injection or bypass ownership/office restrictions.
- **Mass assignment:** no current unvalidated endpoint was established. Explicit safe model field lists have nevertheless been implemented as defense in depth; sensitive fields require trusted assignment paths.
- **Internal completion actions:** internal actions and the public service outcome are distinct visibility choices. Making every internal action public would conflict with the existing privacy model. No change was made to disclose internal notes.
- **File-session encryption and secure-cookie defaults:** these need a production threat/deployment assessment. Local HTTP development intentionally differs from HTTPS deployment. A filesystem compromise is not demonstrated by `encrypt=false` alone.
- **Local root/no-password MySQL:** this remains an explicitly local, loopback-only synthetic environment. A least-privilege account, credentials, network restrictions and HTTPS are production prerequisites, not completed deployment work.

## Verification

### Final report follow-up

- **SQLite: 39 tests / 183 assertions passed. MySQL: 39 tests / 183 assertions passed** on the separate `clsu_feedback_test` database. Five additional regressions cover protected model attributes, success/error response headers, production HTTPS/header behavior, and the existing failed-login Timebox mitigation.
- **All 31 Playwright tests passed in one isolated run** after moving their screenshots to per-test output. An initial screenshot write hit a Windows sharing error on a shared review image; using unique test artifacts resolved it.
- Before/after checks confirmed that the demo database's request/report counts and remembered-login tokens were unchanged across browser verification. No QA listeners remained on ports 5174/8001 after shutdown.
- The TypeScript/Vite production build passed. Configuration probes verified production session encryption/secure-cookie defaults, compatible local HTTP defaults, and explicit environment overrides.
- Production static-site headers still belong in the actual deployment's web-server configuration. No production environment has been configured or certified by this local verification.

### Earlier candidate verification

- **SQLite: 34 tests, 153 assertions passed.**
- **MySQL: 34 tests, 153 assertions passed** using only `clsu_feedback_test` on port 3307.
- Fourteen new `SecurityReviewTest` regressions cover the confirmed access, workflow, limiter, origin, export and test-target safeguards.
- A separate CLI probe intentionally selected `clsu_feedback`; the test bootstrap refused it before RefreshDatabase migrations. The probe did not run tests or migrations against that database.
- **Ten browser tests passed:** existing workflow/export/assets tests (3), staff sign-in tests (3), and remembered-session/logout tests (4). The production TypeScript/Vite build passed.
- `composer audit --working-dir=backend --locked --format=json` returned no advisories or abandoned packages. `npm --prefix frontend audit --package-lock-only --json` returned zero reported vulnerabilities. These are feed results at audit time, not a guarantee that no undisclosed vulnerability exists.
- A read-only aggregate check found **zero matching historical public/internal status-message duplicates** in the known synthetic database. It compared message/request/actor matches; it is not an exhaustive history or log investigation.
- A limited frontend private-key/token pattern scan found zero candidate files. Git tracked-path inspection found zero sensitive runtime paths under the checked patterns. Neither check certifies the complete Git history or every possible secret format.

## Test storage incident and correction

The first new export-limit test exercised synchronous report generation before its storage was faked. It could write the synthetic CSV paths `reports/1.csv`, `reports/2.csv`, and `reports/3.csv`. Test setup now fakes the local disk for all backend tests, and the active-job-cap regression fakes the queue so jobs remain pending.

The authorized local synthetic export 3 was regenerated from its real filters and office scope. Test-generated files for exports 1 and 2 were moved to private quarantine because their original office authorization no longer matched. Their database metadata and authorization were preserved; no permission was widened to regenerate them. Those old files are unavailable and a fresh export should be requested if needed. Subsequent backend runs used isolated storage.

## Remaining boundaries

Playwright creates synthetic requests/reports and rotates remembered-login tokens only inside its separate per-run application database. Its setup creates a new empty SQLite file and migrates it; it never runs destructive migrations against an existing demo database. The existing browser tests no longer require or target the user's active demo instance.

This review did not perform production penetration testing, distributed load testing, an exhaustive concurrent-worker stress test, a full secret-history scan, or production deployment hardening. No schema changes or MCP implementation were introduced. Signed-in anonymous feedback remains a product decision rather than an implemented behavior. The final report has been reconciled; broad UI changes have not been started.
