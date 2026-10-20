# CLSU Feedback Management System and Service Tracker

Working rough version of the CLSU Feedback Management System and Service Tracker. React + TypeScript + Vite frontend, Laravel 12 API, and MySQL 8 database. MCP is disabled, with a reserved interface and documented future adapter boundary.

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

Accounts land in separate workspaces: MISO administrators at `/app/admin`, service personnel at `/app/work`, and requesters at `/app/requester`. The shared `/app` URL forwards to the signed-in account's workspace. One **Staff login** (`/login?role=staff`) serves both administrators and personnel; the authenticated account's stored role determines its destination automatically. Request a service uses requester sign-in. An existing session for an incompatible role offers an explicit account switch. See [login routing](docs/system-architecture.md#login-routing-implemented-prototype).

## Start again on this Windows machine

The local Windows setup runs Laravel using **Laragon's PHP 8.5**. **Composer installs the backend's PHP/Laravel dependencies** (`composer install --working-dir=backend`); **npm installs the React/TypeScript frontend dependencies** (`npm --prefix frontend ci`). Composer manages dependencies; the project launcher starts the application services. The current setup uses the isolated MySQL instance on port 3307 and the browser address `http://127.0.0.1:5173`, rather than a Laragon `.test` virtual host.

```powershell
cd D:\Projects_26\Feedback_Management
.\scripts\init-local-db.ps1
.\scripts\start.ps1
```

The launcher starts the API at port 8000, Vite at 5173, and a report queue worker in hidden processes. Logs and process IDs are in `.local/`. PHP 8.5 is selected explicitly because the system default PHP is older. The default path is `C:\laragon\bin\php\php-8.5.10-Win32-vs17-x64\php.exe`; pass `-PhpPath` to `start.ps1` to override it.

## Fresh installation

Requirements: PHP 8.5 with PDO MySQL/SQLite, mbstring, OpenSSL, XML, curl, fileinfo and zip; Composer; Node 20.19+ or 22.12+; MySQL 8. Tested here with PHP 8.5.10, Node 26.3.1 and MySQL 8.0.30. Exact dependency versions are committed in lockfiles.

```powershell
$env:PATH='C:\laragon\bin\php\php-8.5.10-Win32-vs17-x64;'+$env:PATH
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

## Checks

The [Gemini security review](docs/security-review.md) distinguishes verified fixes, unconfirmed claims, local-development choices, and production prerequisites. Backend test bootstrap rejects unsafe database targets and cached configuration before migrations; local report storage is faked during backend tests. Cookie-authenticated write clients must send the configured trusted Origin or a trusted Referer.

The [Gemini security candidate review](docs/security-review.md) distinguishes verified fixes, unconfirmed claims, local-development choices, and production prerequisites. Backend test bootstrap rejects unsafe database targets and cached configuration before migrations; local report storage is faked during backend tests. Cookie-authenticated write clients must send the configured trusted Origin or a trusted Referer.

The [bug audit and fixes](docs/bug-audit.md) records the confirmed defects corrected during the 20 October 2026 review. Run `./scripts/test-local-runtime.ps1` for non-destructive launcher checks. Playwright automatically starts an isolated test app on ports 5174/8001, with a new SQLite database, sessions, cache, queue and private exports under `.local/e2e/<run-id>/`. It leaves the demo's MySQL records and remembered-login tokens alone. Ports must be free; existing services are never reused. The runner uses the same PHP 8.5 executable, overridable through `PORTAL_TEST_PHP`. Logs are in the run's `services.log`; screenshots are in `frontend/test-results/`.

```powershell
npm --prefix frontend run build
php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml
npm --prefix frontend run test:e2e
```

The default PHP test configuration uses an in-memory SQLite database. The suite also runs against the separately allowlisted MySQL test database (`clsu_feedback_test` on port 3307); unsafe targets are rejected. Browser tests use installed Chrome and their own isolated instance. Their SQLite integration coverage complements the separate MySQL feature suite. Old `.local/e2e/` run artifacts can be removed when no longer needed; preserve any logs required for review.

Screenshots are in `.impeccable/review/`. Original design context and an asset provenance manifest are in `design-reference/`; 262 Figma images/SVGs are stored locally in `frontend/public/assets/figma/`. Runtime assets do not depend on temporary Figma links.

Shared window-size and zoom reflow rules live in `frontend/src/responsive.css`, loaded after the base and dashboard styles. The welcome illustration scales through its wrapper in `App.tsx`, retaining the original Figma assets. To run the viewport checks: `npm --prefix frontend run test:e2e -- responsive.spec.ts`.

## Prototype boundaries

The [latest security audit follow-up](docs/security-audit-follow-up.md) records the new hardening and remaining deployment decisions. Non-development startup rejects unsafe configuration; additional origins and trusted proxy IPs/CIDRs must be explicitly configured. Local/testing defaults remain usable. Real hosting/TLS/static-SPA header configuration is still a deployment responsibility.

Settings, system evaluation, password recovery, attachments, outbound email, and MCP execution are reserved for later work. Priority policies and status transitions are configurable demo choices pending MISO validation. See [implementation notes](docs/implementation-notes.md) for the physical-schema refinements, access-model simplifications, report definitions, implemented workflows, and deferred scope. This rough build is not a production deployment.

## Documentation

| Document | Purpose |
| --- | --- |
| [Agent Instructions](AGENTS.md) | Repository guidance for future coding sessions, including project references, implementation rules, and verification expectations. |
| [System Architecture](docs/system-architecture.md) | System scope, technology choices, modules, workflows, access controls, MCP capabilities, deployment, and verification requirements. |
| [Database Design](docs/database-design.md) | Proposed core and full ERDs, entities, relationships, keys, integrity rules, assumptions, and acceptance scenarios. |
| [Database Normalization](docs/database-normalization.md) | Normalization assessment, recommended refinements, intentional duplication, and conditions for a formal 3NF assessment. |

Read the architecture first, then the database design and normalization assessment. The database design remains a proposal; unresolved choices and normalization refinements must be addressed before treating it as a finalized implementation schema.
