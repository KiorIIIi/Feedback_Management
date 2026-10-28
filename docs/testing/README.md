# Testing and verification

Use [verification history](verification.md) for results that actually ran, [Iteration 1](../changes/iteration-01/sprint-01/001-reporting-and-feedback.md#verification-record) for the latest feature evidence, and [story verification](../agile/sprints/EPIC-002/sprint-01/sprint-01.md) for scoped acceptance records.

## Commands

Run from the repository root, using the configured PHP 8.5 executable described in the [setup guide](../../README.md#start-again-on-this-windows-machine):

```powershell
npm --prefix frontend run build
php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml
npm --prefix frontend run test:e2e
python scripts/check-docs.py
./scripts/test-local-runtime.ps1
./scripts/test-public-secret-acl.ps1
```

PHPUnit defaults to in-memory SQLite and rejects unsafe database targets. MySQL feature checks use only `clsu_feedback_test` on loopback port 3307. The separately enabled provisioning integration test in MySQL CI creates and cleans up only fresh random synthetic databases/users; it requires explicit isolated-test operator credentials. Playwright launches isolated frontend/API/queue storage on ports 5174/8001; it must not reuse or reset the working demo database. Use the dated verification records for actual commands, results and coverage limits.

Public-demo checks are separately described in the [repository README](../../README.md#temporary-public-demo-hosted-on-this-pc). They deliberately add labeled synthetic records to the isolated public demo. Private current screenshots and report exports stay under ignored `.local/public-host/`; historical tracked design captures remain under `.impeccable/review/`.

## Audit records

The [live notification change record](../changes/iteration-03/sprint-03/005-live-notifications.md) tracks cross-session updates, office/privacy restrictions, unread totals, transactional rollback and editing/connection regressions.

The [admin assistant setup record](../changes/iteration-03/sprint-03/006-admin-assistant-setup.md) tracks password/version/secret controls, ChatGPT OAuth/client revocation and local-only read-tool/provider-denial checks. Provider endpoints are disabled; local diagnostics make no provider requests.

- [Bug audit and fixes](audits/bug-audit.md)
- [Security review and findings disposition](audits/security-review.md)
- [Supplied security audit](audits/security-audit.md), preserved as evidence
- [Security audit follow-up](audits/security-audit-follow-up.md)
- [Independent security review — 24 October 2026](audits/deepseek-security-review-2026-10-24.md)
- [Security remediation — 24 October 2026](../changes/iteration-03/sprint-03/004-security-audit-remediation.md)

## Continuous verification

[Project verification](../../.github/workflows/laravel.yml) runs for pushes and pull requests targeting `front-main` or `main`, and supports manual dispatch. Backend jobs use PHP 8.5 and run the suite against isolated SQLite and a MySQL 8 service (`clsu_feedback_test`, loopback port 3307). Other jobs check locked dependency advisories, the frontend production build, the isolated Chrome browser suite, documentation links, and Windows launcher/private-file ACL checks. Actions are pinned to verified upstream commit SHAs, checkout does not persist credentials, and the workflow token has read-only repository contents permission.

Workflow configuration is distinct from a successful GitHub Actions run. Consult the remediation record for actual local results and remote-run evidence. Branch protection and required-check settings are GitHub administration decisions; a workflow alone does not enforce merge restrictions.

Treat historical counts as dated results; do not add counts from overlapping staged runs or present an earlier test run as new evidence.
