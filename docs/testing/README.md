# Testing and verification

Use [verification history](verification.md) for results that actually ran, [Iteration 1](../changes/iteration-01/sprint-01/001-reporting-and-feedback.md#verification-record) for the latest feature evidence, and [story verification](../agile/sprints/EPIC-002/sprint-01/sprint-01.md) for scoped acceptance records.

## Commands

Run from the repository root, using the configured PHP 8.5 executable described in the [setup guide](../../README.md#start-again-on-this-windows-machine):

```powershell
npm --prefix frontend run build
php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml
npm --prefix frontend run test:e2e
python scripts/check-docs.py
```

PHPUnit defaults to in-memory SQLite and rejects unsafe database targets. MySQL checks use only `clsu_feedback_test` on loopback port 3307. Playwright launches isolated frontend/API/queue storage on ports 5174/8001; it must not reuse or reset the working demo database. These are documented commands, not a claim they were rerun for this documentation-only change.

Public-demo checks are separately described in the [repository README](../../README.md#temporary-public-demo-hosted-on-this-pc). They deliberately add labeled synthetic records to the isolated public demo. Private current screenshots and report exports stay under ignored `.local/public-host/`; historical tracked design captures remain under `.impeccable/review/`.

## Audit records

- [Bug audit and fixes](audits/bug-audit.md)
- [Security review and findings disposition](audits/security-review.md)
- [Supplied security audit](audits/security-audit.md), preserved as evidence
- [Security audit follow-up](audits/security-audit-follow-up.md)

Treat historical counts as dated results; do not add counts from overlapping staged runs or present an earlier test run as new evidence.
