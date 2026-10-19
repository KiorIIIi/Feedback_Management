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

Sign-in remembers your account by default; uncheck **Remember me** on a shared device. Refreshing, returning to the app, and browser Back preserve a valid session. Temporary connection failures offer a retry instead of treating you as signed out. Signing out clears the session and remembered login.

Accounts land in separate workspaces: MISO administrators at `/app/admin`, service personnel at `/app/work`, and requesters at `/app/requester`. The shared `/app` URL forwards to the signed-in account's workspace. Admin and personnel sign-in links verify the selected role; an existing session for another role offers an explicit account switch.

## Start again on this Windows machine

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

```powershell
npm --prefix frontend run build
php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml
npm --prefix frontend run test:e2e
```

The default PHP test configuration uses an in-memory SQLite database. The suite also ran successfully against a separate MySQL test database (`clsu_feedback_test`); never point the test suite at the demo or a production database. Browser tests use installed Chrome and a running local instance. They create a synthetic request and private report, leaving reviewable demo records.

Screenshots are in `.impeccable/review/`. Original design context and an asset provenance manifest are in `design-reference/`; 262 Figma images/SVGs are stored locally in `frontend/public/assets/figma/`. Runtime assets do not depend on temporary Figma links.

## Prototype boundaries

Settings, system evaluation, password recovery, attachments, outbound email, and MCP execution are reserved for later work. Priority policies and status transitions are configurable demo choices pending MISO validation. See [implementation notes](docs/implementation-notes.md) for the physical-schema refinements, access-model simplifications, report definitions, implemented workflows, and deferred scope. This rough build is not a production deployment.

## Documentation

| Document | Purpose |
| --- | --- |
| [Agent Instructions](AGENTS.md) | Repository guidance for future coding sessions, including project references, implementation rules, and verification expectations. |
| [System Architecture](docs/system-architecture.md) | System scope, technology choices, modules, workflows, access controls, MCP capabilities, deployment, and verification requirements. |
| [Database Design](docs/database-design.md) | Proposed core and full ERDs, entities, relationships, keys, integrity rules, assumptions, and acceptance scenarios. |
| [Database Normalization](docs/database-normalization.md) | Normalization assessment, recommended refinements, intentional duplication, and conditions for a formal 3NF assessment. |

Read the architecture first, then the database design and normalization assessment. The database design remains a proposal; unresolved choices and normalization refinements must be addressed before treating it as a finalized implementation schema.
