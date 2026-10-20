# Bug audit and fixes — 20 October 2026

This pass reviewed the implemented local prototype's authentication, record access, request lifecycle, notifications, reports, responsive behavior, and Windows launchers. The findings below were corrected in code. Test coverage and final results are recorded in [verification](../verification.md). This is a scoped review, not a claim that every possible defect has been eliminated.

## Access, privacy, and backend behavior

| ID | Severity | Reproduction / previous behavior | Correction |
| --- | --- | --- | --- |
| B01 | High | Change status while submitting an internal staff note. The private note was copied into a requester-visible status event. | Public status events use the status change; private messages retain internal visibility. |
| B02 | High | Disable an account after login. Its existing session could continue calling protected endpoints. | Active-account middleware ends the session and returns 401. |
| B03 | High | Move personnel to another handling office. Historical assignments still granted access to the old office's records. | Personnel access also requires the account's current handling office. |
| B04 | High | Reassign a job and complete it under another person. Earlier assignees regained record access on completion. | Completed-job access is restricted to the final assignee within the current office. |
| B05 | High | Send a write from the same hostname on a different port or scheme. Host-only origin comparison allowed it. | Compare complete HTTP origins, including scheme and effective port. Configured frontend/proxy access remains supported. |
| B06 | Medium | Review priority when future or expired matrix versions exist. An ineffective version could be selected. | Select only the matrix effective at the review time. |
| B07 | Medium | Call a protected API without an Accept header while signed out. Authentication attempted a missing login route and returned 500. | Protected API failures return JSON 401 consistently. |
| B08 | Medium | Mark a notification read twice in the same second on MySQL. The second call could return 404; later calls changed the original read time. | Check ownership/existence independently, then set read_at only when null. Retries preserve the first read time. |

Backend regressions are in `backend/tests/Feature/BugAuditTest.php`; existing lifecycle, stale-version, privacy, evaluation, export and ownership checks remain in `PortalWorkflowTest.php`. No database schema migration was needed.

## Session and frontend recovery

| ID | Severity | Reproduction / previous behavior | Correction |
| --- | --- | --- | --- |
| B09 | Medium | Let a session expire while navigating. The UI retained the signed-in account after an API 401. | Clear authenticated UI state on a current-session 401 and return protected routes to sign-in. |
| B10 | Medium | Sign in again while an older request is pending, then receive its delayed 401. It cleared the new login. | Requests capture an authentication generation; obsolete unauthorized responses cannot invalidate the new session. |
| B11 | Medium | Confirm logout during a server/network failure. The rejection was unhandled and gave no recovery feedback. | Show an error, restore the logout control, and allow retry. Already-expired sessions can exit normally. |
| B12 | Medium | Receive HTML instead of JSON with a successful HTTP status. The API helper returned a fabricated success-shaped object. | Treat unreadable JSON as an explicit API error with retry guidance. |
| B13 | Medium | View raw database notification timestamps in a browser outside the Philippines. Browser-local parsing shifted the displayed Manila time/date. | Parse offset-free SQL timestamps as Asia/Manila; retain explicit offsets on ISO timestamps. |
| B14 | Medium | Load requests, change a filter, and fail the next fetch. Earlier unrelated rows or a false empty state remained visible. | Scope loaded results to their request path, abort obsolete loads, and distinguish fetch errors from empty results. |
| B15 | Medium | Fail Mark read, or read a notification without leaving the page. Failures were unhandled and the navigation badge stayed stale. | Add pending/error/retry behavior and refresh the badge after a successful read. |
| B16 | Medium | Save an update successfully, then fail the catalog or record refresh. A committed update appeared to fail, or its confirmation disappeared. | Preserve the saved confirmation, distinguish workspace-refresh failure, and offer Retry loading request if the detail fetch fails. |
| B17 | Medium | Queue a report and change the reporting period while metrics load. Unmounted controls discarded the pending export. | Keep report controls mounted while metrics refresh. |
| B18 | Medium | Let report polling run slowly or recover from a transient error. Interval polls could overlap and recovered errors remained visible. | Poll sequentially, cancel on cleanup, and clear errors when a later poll succeeds. |
| B19 | Medium | Fail the requester dashboard's initial fetch. There was no retry control. | Add a retry action without forcing sign-out. |
| B20 | Medium | Open a rating URL as staff, or feedback history as personnel. The UI displayed an unusable form or a guaranteed forbidden page. | Redirect incompatible roles to their own workspace; backend authorization remains enforced. |
| B21 | Medium | Navigate between request IDs while reusing a mounted form. Previous success/error or rating selections could carry over. | Reset request-specific form state when the route ID changes. |

Browser regressions: `frontend/tests/auth-errors.spec.ts`, `auth-race.spec.ts`, and `ui-bugs.spec.ts`. These use isolated API fixtures to reproduce failure paths; existing browser suites exercise the running Laravel/MySQL system and its queue worker.

## Windows startup

| ID | Severity | Reproduction / previous behavior | Correction |
| --- | --- | --- | --- |
| B22 | High | Occupy an application/database port with another process. Launchers trusted the port alone; database initialization could connect to an unrelated instance. | Verify executable plus project path or MySQL data directory before reuse. Reject a conflicting service without stopping it. |
| B23 | Medium | Start services slowly, reuse a damaged PID file, or omit installed dependencies. Startup could report success prematurely or fail unclearly. | Add bounded HTTP readiness checks, tolerant PID parsing, explicit dependency errors, quoted paths, and isolated MySQL configuration. |
| B24 | Medium | Run backend tests alongside the local app. Hard-coded file caching ignored CACHE_STORE=array and shared live rate-limit counters, causing unrelated logins/tests to receive 429. | Honor CACHE_STORE, with file as the normal default; feature tests use their configured in-memory store. Add a cache-isolation regression. |

The launchers share `scripts/local-runtime.ps1`. `scripts/test-local-runtime.ps1` exercises ownership rejection, PID recovery and readiness with mocks; it does not start, stop, or modify real services/databases.

## Review boundaries

The Figma composition, exported assets, role destinations, and configured demo workflow policy were preserved. MySQL feature tests use only `clsu_feedback_test`; browser workflow tests add synthetic demo requests and private reports. Existing stored private notes created before the fix were not retroactively rewritten.

Deferred functionality remains deferred: operational MCP, email, password recovery, private attachments, and final production policy/configuration. This pass did not certify load behavior, all simultaneous-write races, a full accessibility audit, backup restoration, production deployment, or exact equivalence across every Figma frame.
