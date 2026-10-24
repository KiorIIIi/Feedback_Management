# Security audit remediation — 24 October 2026

Iteration 03 / Sprint 03. User authorized correcting the findings in the [independent DeepSeek review](../../../testing/audits/deepseek-security-review-2026-10-24.md). The original report is preserved as received; its test/advisory results remain attributed to that review.

## Changes and finding dispositions

| Finding | Resulting behavior |
| --- | --- |
| F-01 — Broken CI | Replaced the PHP 8.0/root-directory template with PHP 8.5 backend verification on SQLite and isolated MySQL 8, frontend build, locked Composer/npm advisory checks, isolated Chrome E2E, docs links and Windows runtime/ACL checks. Push and PR triggers cover `front-main` and `main`; pinned actions use read-only contents permission and do not persist checkout credentials. This does not configure branch protection. |
| F-02 — Permissive MCP flag | Validated boolean parsing makes `off`/`no`/`0` false; unknown strings reject startup with a setting-name-only error. Production validates the boolean type; middleware requires literal true. Intentional pilot enablement still uses the existing authentication, grants and HTTPS rules. Temporary public-demo initialization explicitly sets false on creation/reuse. |
| F-03 — Demo secret-file permissions | Before reading/writing credentials, initialization restricts the runtime directory and existing secret files to the current Windows identity, or 0700/0600 on POSIX. New JSON files are written in the private directory and atomically renamed after complete encoding/writing. Windows ACL changes reject paths outside project-local runtime directories and reject junction/reparse paths. |
| F-04 — Interrupted invocation rows | A trusted CLI command conditionally closes stale unfinished calls with `failed`/`interrupted_unknown`. This records uncertainty, not proof that execution failed. Recent and terminal rows remain intact. Finalization uses a conditional update so a late response cannot overwrite recovered evidence or return private content without a final audit. Hourly scheduler registration requires an operator-managed scheduler; it is not automatically running on this PC. |
| F-05 — Failed demo provisioning | Runtime users receive only SELECT/INSERT/UPDATE/DELETE from creation. Existing trusted operator credentials perform migration/seeding only in the initializer process. Recoverable failures remove only the database/user created by that attempt and incomplete credential files. Cleanup failures are reported without secret values. Resource names are validated, and an exclusive initialization lock prevents overlapping provisioning. |

Database-level grants also escape underscore wildcards when MySQL's `partial_revokes` setting is off; with partial revokes enabled, the literal database identifier is used. This follows [MySQL's GRANT rules](https://dev.mysql.com/doc/refman/8.0/en/grant.html). Initialization rejects cached configuration and verifies the effective database target before migrations. An opt-in integration regression in the MySQL CI job uses only newly generated synthetic databases/users to check runtime DML access, schema-operation denial, rejection of access to a similarly named database, and cleanup after an intentional failure. It never deploys a public demo.

The first full browser run found an existing ambiguous `Not rated` text locator, which matched a filter option and multiple score labels while navigating. The regression now waits for the selected detail heading and asserts its score within the message header. No product behavior or assertion expectation was relaxed.

No dependencies, schema, priority/evaluation policy, remembered-login lifetime, deployed services, or operational MCP tools are changed. Existing demo credentials are protected on the next authorized launch/initialization; this code change does not restart or republish a demo.

## Verification

| Check | Actual result |
| --- | --- |
| Full backend PHPUnit suite, in-memory SQLite | PASS with one intentional skip: 87 tests, 651 assertions, 1 skipped live-MySQL provisioning integration test. The isolated MySQL server was offline locally. |
| Provisioning regressions after the final path-scope check | PASS: 7 tests, 34 assertions, 1 intentional MySQL skip. |
| Full isolated Playwright/Chrome suite | PASS: all 36 tests in one final run, including sessions, role routing, privacy, report exports and responsive behavior. |
| `npm --prefix frontend run build` | PASS: TypeScript and Vite production build; generated tracked TypeScript metadata restored rather than included in the change. |
| Locked Composer advisory audit | PASS: no advisories or abandoned packages reported. |
| Locked frontend npm advisory audit | PASS: zero reported vulnerabilities across 166 dependencies. |
| Windows private-file ACL checks | PASS: 6 checks covering existing files, inheritance for a newly created file and scope rejection. |
| Non-destructive launcher checks | PASS: 13 checks; no live services/databases changed. |
| Workflow validation | PASS: parsed with the YAML implementation bundled in installed Playwright; checked branch triggers, pinned action references and read-only contents permission. This is not GitHub Actions execution. |
| Documentation links/anchors | PASS: `python scripts/check-docs.py` checked 76 Markdown files and 418 local links/anchors. |
| PHP syntax and Git whitespace | PASS for changed PHP scripts and `git diff --check`. |

Initial attempts and corrections: the first backend run failed because Windows PowerShell inherited PowerShell 7's module search path; the helper now imports its own security module explicitly and uses a process-scoped `RemoteSigned` policy. An initial sandboxed browser startup could not share its Node/PHP-generated files; the final isolated suite ran outside that sandbox. The first complete browser run found the ambiguous locator described above (35 passed, 1 failed); the corrected full rerun passed all 36. Restricted-network advisory attempts failed DNS resolution; both final advisory queries succeeded outside that restriction. These unsuccessful attempts are not counted as passing checks.

GitHub's existing workflow was manually disabled. It was re-enabled through the authenticated Actions API and [dispatched on `front-main`](https://github.com/KiorIIIi/Feedback_Management/actions/runs/40059449105) at commit `7c9434d`. Windows, frontend, documentation and Linux SQLite jobs passed (SQLite: 87 tests, 653 assertions, one expected MySQL skip). The first remote run exposed two verification setup problems: the MySQL runtime-account test connected through Docker's bridge instead of its required loopback path, and two browser tests navigated while asynchronous logout was still redirecting. The MySQL fixture now runs the runtime client on loopback inside the service container, and those browser tests explicitly wait for the logout landing URL before navigating. Authentication/grant restrictions and product behavior are unchanged. A subsequent local targeted browser repeat timed out during isolated-server startup; it is not counted as a pass. The complete remote browser run below verifies the final tests.

### Final GitHub Actions evidence

The push of code/test commit **`ee4a4b8709a81a95255ee69fd9931470d09373c8`** automatically triggered [run 40066862414](https://github.com/KiorIIIi/Feedback_Management/actions/runs/40066862414), which completed successfully on 24 October 2026. All six jobs passed:

| Job | Observed result |
| --- | --- |
| Backend (MySQL) | PASS: 87 tests, 668 assertions, no skipped tests on isolated MySQL 8.0.46 / PHP 8.5.12. The real provisioning regression proved DML access, schema-operation denial, denial for a similarly named neighboring database, and removal of the created database/user after intentional failure. |
| Backend (SQLite) | PASS: 87 tests, 653 assertions, one expected MySQL-only skip. The two extra assertions versus Windows check POSIX directory/file modes. |
| Browser | PASS: all 36 isolated Chrome tests on Linux, with no retry configuration. |
| Frontend | PASS: locked install, advisory audit and production build. |
| Documentation | PASS: local links/anchors. |
| Windows runtime | PASS: launcher and private-file ACL checks. |

No application code changed after this verified commit. The final evidence-only documentation update is checked locally with the documentation checker and Git whitespace check; it does not rerun application tests. The workflow remains enabled for subsequent development pushes and pull requests.

No public tunnel, credential issuance, deployment, production database reset, or real-client MCP connection was performed.

## Boundaries

An audit finding no confirmed high/critical vulnerability is encouraging evidence within its coverage, not a production-security certification. Hosting/TLS/proxies, real-user provider handling, final remembered-login policy, load/concurrency testing and a full Git-history secret scan remain separate work. An uncatchable process kill or lost database connection may still leave demo resources requiring trusted operator inspection; runtime accounts never receive schema-management grants. Owner-only ACLs do not isolate secrets from other processes running as that same user or privileged OS administrators.

## Implementation references

- [CI workflow](../../../../.github/workflows/laravel.yml)
- [MCP configuration and recovery operation](../../../architecture/mcp-implementation.md)
- [Public-demo initializer](../../../../scripts/init-public-demo.php), [security helpers](../../../../scripts/public-demo-security.php), [Windows ACL helper](../../../../scripts/protect-public-secrets.ps1)
- [Backend security regressions](../../../../backend/tests/Feature/DeepseekSecurityFixTest.php), [provisioning regressions](../../../../backend/tests/Feature/PublicDemoProvisioningTest.php), [Windows ACL checks](../../../../scripts/test-public-secret-acl.ps1)

Implementation references: [validated PHP boolean filter](https://www.php.net/manual/en/filter.constants.validation.php), [Windows ACL inheritance protection](https://learn.microsoft.com/en-us/dotnet/api/system.security.accesscontrol.objectsecurity.setaccessruleprotection). Action pins were resolved through the upstream GitHub tag APIs on 24 October: [checkout v4](https://api.github.com/repos/actions/checkout/git/ref/tags/v4), [setup-node v4](https://api.github.com/repos/actions/setup-node/git/ref/tags/v4), [setup-php v2](https://api.github.com/repos/shivammathur/setup-php/git/ref/tags/v2) and its annotated-tag commit.

[Sprint overview](sprint-overview.md) · [Testing index](../../../testing/README.md)
