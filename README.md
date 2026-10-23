# CLSU Feedback Management System and Service Tracker

Working rough version of the CLSU Feedback Management System and Service Tracker. React + TypeScript + Vite frontend, Laravel 12 API, and MySQL 8 database. An opt-in authenticated read-only MCP pilot is implemented; the embedded assistant and operational MCP writes remain deferred.

The implementation follows the supplied Figma layouts and uses locally exported Figma assets. Requesters can submit, track, clarify, and evaluate services. MISO can review, prioritize, assign, and export scoped reports; personnel can update their assigned work. The user delegated configurable demo workflow rules for this prototype.

## Open the local system

[MISO Service Portal](http://127.0.0.1:5173)

The local instance uses a project-owned MySQL database on `127.0.0.1:3307`. Data and logs live in `.local/`, outside version control. Existing databases on port 3306 are untouched.

| Demo role | Email | Password |
| --- | --- | --- |
| MISO administrator | `admin@clsu.demo` | `DemoPass!2026` |
| Requester | `andrea@clsu.demo` | `DemoPass!2026` |
| Service personnel | `mark@clsu.demo` | `DemoPass!2026` |
| Other requester, for ownership checks | `jamie@clsu.demo` | `DemoPass!2026` |

All accounts and service records are synthetic. These credentials are for the local demo only.

**Remember me** is off by default; enable it when you want persistent sign-in on a trusted device. Refreshing, returning to the app, and browser Back preserve a valid normal session. Temporary connection failures offer a retry instead of treating you as signed out. Signing out clears the session and remembered login.

Accounts land in separate workspaces: MISO administrators at `/app/admin`, service personnel at `/app/work`, and requesters at `/app/requester`. The shared `/app` URL forwards to the signed-in account's workspace. One **Staff login** (`/login?role=staff`) serves both administrators and personnel; the authenticated account's stored role determines its destination automatically. Request a service uses requester sign-in. An existing session for an incompatible role offers an explicit account switch. See [login routing](docs/architecture/system-architecture.md#login-routing-implemented-prototype).

## Start again on this Windows machine

The local Windows setup runs Laravel using **Laragon's PHP 8.5**. **Composer installs the backend's PHP/Laravel dependencies** (`composer install --working-dir=backend`); **npm installs the React/TypeScript frontend dependencies** (`npm --prefix frontend ci`). Composer manages dependencies; the project launcher starts the application services. The current setup uses the isolated MySQL instance on port 3307 and the browser address `http://127.0.0.1:5173`, rather than a Laragon `.test` virtual host.

```powershell
cd D:\Projects_26\Feedback_Management
.\scripts\init-local-db.ps1
.\scripts\start.ps1 -PhpPath 'C:\laragon\bin\php\php-8.5.12-Win32-vs17-x64\php.exe'
```

The launcher starts the API at port 8000, Vite at 5173, and a report queue worker in hidden processes. Logs and process IDs are in `.local/`. The launchers explicitly select the patched PHP 8.5.12 runtime. The Windows user PATH also selects this version after reopening the terminal. The default path is `C:\laragon\bin\php\php-8.5.12-Win32-vs17-x64\php.exe`; pass `-PhpPath` to `start.ps1` to override it.

See the [22 October PHP security update](docs/project/php-runtime-security.md) for the advisory, verified versions, and retired-runtime details.

## Fresh installation

Requirements: PHP 8.5.12 or a newer patched PHP 8.5 release with PDO MySQL/SQLite, mbstring, OpenSSL, GD, DOM/XML, curl, fileinfo and zip; Composer; Node 20.19+ or 22.12+; MySQL 8. Tested here with PHP 8.5.12, Node 26.3.1 and MySQL 8.0.30. Exact dependency versions are committed in lockfiles.

```powershell
$env:PATH='C:\laragon\bin\php\php-8.5.12-Win32-vs17-x64;'+$env:PATH
composer install --working-dir=backend
npm --prefix frontend ci
Copy-Item backend/.env.example backend/.env
# Configure DB_* in backend/.env for a new, empty development database.
# For the bundled isolated DB, run init-local-db.ps1 and set DB_PORT=3307.
php backend/artisan key:generate
php backend/artisan migrate
# Optional synthetic seed: set DEMO_SEED=true in backend/.env first.
php backend/artisan db:seed
```

Seeding refuses an existing populated database. On other environments, run `php artisan serve` from `backend`, `npm run dev` from `frontend`, and `php artisan queue:work` from `backend`. Both the browser and API must remain on the same origin through the Vite proxy. For deployment, route `/api` to Laravel and serve the frontend build with an SPA fallback; HTTPS and secure session cookies are required.

## Temporary public demo hosted on this PC

The device-hosted demo uses the complete React/Laravel/MySQL app through a temporary HTTPS Cloudflare Quick Tunnel. This is a synthetic demonstration, not an approved production campus service. The PC and its internet connection must remain on. The public URL changes when the tunnel restarts; no domain or Cloudflare account is needed. Nothing is published to GitHub Pages.

```powershell
# One-time project-local client installation; verifies the official SHA-256.
.\scripts\install-cloudflared.ps1
# Build and start the isolated public demo.
.\scripts\start-public.ps1 -PhpPath 'C:\laragon\bin\php\php-8.5.12-Win32-vs17-x64\php.exe' -UseWindowsDns
# Disconnect the public link and stop only its web process and queue worker.
.\scripts\stop-public.ps1 -PhpPath 'C:\laragon\bin\php\php-8.5.12-Win32-vs17-x64\php.exe'
```

The client is pinned to cloudflared 2026.10.2 (Windows amd64). `-UseWindowsDns` is a version-specific workaround for this machine's connector DNS failures: Windows resolves the official Cloudflare edge names, which are passed through the client's explicit-edge option. TLS verification stays enabled and system DNS/firewall settings are unchanged. Omit the switch where ordinary connector DNS works. Hostname propagation can take time; startup verifies the public HTTPS `/up` endpoint and stops the public processes if it fails.

The launcher uses the project-owned MySQL server on loopback port 3307, creates a separate `clsu_public_demo_<random>` database and scoped MySQL user, and seeds only synthetic records on first use. Public runtime gets only SELECT/INSERT/UPDATE/DELETE privileges on that new database. All account passwords are replaced with random passwords. Existing local records and credentials are retained. Restarting reuses the public demo database and credentials and updates its trusted origin.

The frontend build is copied into `.local/public-host/site`; a loopback-only PHP 8.5 web process on port 4180 serves that artifact and routes `/api` to Laravel. It serves the built frontend rather than the Vite development server. A separate worker processes report jobs. Runtime uses `APP_ENV=production`, debug off, a new application key, encrypted Secure/HttpOnly session cookies, explicit loopback proxy trust, trusted-origin checks, and security headers for the SPA and API. Sessions, cache, logs, and private exports are isolated under `.local/public-host`. Source files, environment files, and credentials are outside the document root.

The public URL is saved in `.local/public-host/url.txt`. Sign-in credentials are in **`.local/public-host/access.json`**; keep that file private. `.local/public-host/runtime.json` contains deployment secrets and must also stay private. These files are ignored by Git. Share only the generated HTTPS URL and the demo account access intended for each tester.

After starting, run `npm --prefix frontend run test:public` for browser verification through the public URL with installed Chrome. This intentionally creates a clearly labeled synthetic request, progresses it using synthetic staff accounts, evaluates it, and generates a private report in the isolated public demo. It does not target the ordinary local demo. Evidence is written to `.local/public-host/verification.json` and `verified-desktop.png`. Do not report those checks as passed unless that run succeeds.

Quick Tunnels have no uptime guarantee. The PHP built-in server is used for this temporary, low-volume demo; this launcher does not establish continuous hosting, automatic boot recovery, backups, final MISO policy approval, or production readiness. See [Cloudflare Quick Tunnels](https://developers.cloudflare.com/tunnel/get-started/quick-tunnels/).

## Service evaluation reports

The [Iteration 1 change log](docs/changes/iteration-01/iteration-overview.md) records report/category additions, the full feedback reading view, changed behavior, verification and remaining boundaries.

Open **Reports & analytics** as a MISO administrator. Choose a rolling period or **Calendar month**, then export PDF, Excel (`.xlsx`) or a CSV request register. The queue worker prepares private downloads. Reports group requests by staff-assigned category, official priority, current status and personnel, with verified-rating and calendar-hour resolution averages. Expand **How these figures are calculated** for denominators and exclusions.

Create office categories at the bottom of that page, then assign a **Request category** in an open request's review form. Categories are separate from service types; existing records are Uncategorized. See [report definitions and boundaries](docs/project/implementation-notes.md#service-evaluation-reporting-implemented-on-20-october).

For an existing development installation, run `php backend/artisan migrate` after updating dependencies. The isolated public demo can apply additive migrations with `php scripts/migrate-public.php`; this CLI reads local migration credentials without giving them to the public web process. Restart the report worker after backend updates and rebuild the frontend. Never rerun the demo seed on populated data.

## Checks

The [Gemini security review](docs/testing/audits/security-review.md) distinguishes verified fixes, unconfirmed claims, local-development choices, and production prerequisites. Backend test bootstrap rejects unsafe database targets and cached configuration before migrations; local report storage is faked during backend tests. Cookie-authenticated write clients must send the configured trusted Origin or a trusted Referer.

The [bug audit and fixes](docs/testing/audits/bug-audit.md) records the confirmed defects corrected during the 20 October 2026 review. Run `./scripts/test-local-runtime.ps1` for non-destructive launcher checks. Playwright automatically starts an isolated test app on ports 5174/8001, with a new SQLite database, sessions, cache, queue and private exports under `.local/e2e/<run-id>/`. It leaves the demo's MySQL records and remembered-login tokens alone. Ports must be free; existing services are never reused. The runner uses the same PHP 8.5 executable, overridable through `PORTAL_TEST_PHP`. Logs are in the run's `services.log`; screenshots are in `frontend/test-results/`.

```powershell
npm --prefix frontend run build
php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml
npm --prefix frontend run test:e2e
```

The default PHP test configuration uses an in-memory SQLite database. The suite also runs against the separately allowlisted MySQL test database (`clsu_feedback_test` on port 3307); unsafe targets are rejected. Browser tests use installed Chrome and their own isolated instance. Their SQLite integration coverage complements the separate MySQL feature suite. Old `.local/e2e/` run artifacts can be removed when no longer needed; preserve any logs required for review.

Screenshots are in `.impeccable/review/`. Original design context and an asset provenance manifest are in `design-reference/`; 262 Figma images/SVGs are stored locally in `frontend/public/assets/figma/`. Runtime assets do not depend on temporary Figma links.

Shared window-size and zoom reflow rules live in `frontend/src/responsive.css`, loaded after the base and dashboard styles. The welcome illustration scales through its wrapper in `App.tsx`, retaining the original Figma assets. To run the viewport checks: `npm --prefix frontend run test:e2e -- responsive.spec.ts`.

## Prototype boundaries

The [latest security audit follow-up](docs/testing/audits/security-audit-follow-up.md) records the new hardening and remaining deployment decisions. Non-development startup rejects unsafe configuration; additional origins and trusted proxy IPs/CIDRs must be explicitly configured. Local/testing defaults remain usable. Real hosting/TLS/static-SPA header configuration is still a deployment responsibility.

Settings, system evaluation, password recovery, attachments, outbound email, embedded AI chat and operational MCP writes are reserved for later work. The [MCP pilot guide](docs/architecture/mcp-implementation.md) documents the disabled-by-default HTTP endpoint, five scoped read tools, expiring credentials, revocation and invocation auditing. No provider is configured or public MCP deployment enabled. Priority policies and status transitions are configurable demo choices pending MISO validation. See [implementation notes](docs/project/implementation-notes.md) for the physical-schema refinements, access-model simplifications, report definitions, implemented workflows, and deferred scope. This rough build is not a production deployment.

## Documentation

Start with the [documentation index](docs/README.md). The [documentation map](docs/project/documentation-map.md) records relocated files.

| Area | Contents |
| --- | --- |
| [Agile](docs/agile/README.md) | Epics, product backlog, sprint records, implementation plans and story tests |
| [Architecture](docs/architecture/README.md) | System architecture, database design/normalization and historical exports |
| [Changes](docs/changes/README.md) | Iteration 1 and documentation change records |
| [Project](docs/project/README.md) | Implementation decisions and project guidance |
| [Requirements](docs/requirements/README.md) | Original research paper and requirement traceability |
| [Testing](docs/testing/README.md) | Verification evidence, test commands and audit records |

[Agent instructions](AGENTS.md), [product context](PRODUCT.md) and [design context](DESIGN.md) remain at the repository root. Read the architecture before database design/normalization; proposed policies and diagrams are distinct from implemented and verified features. Epic/sprint records for Iteration 1 are explicitly retrospective.
