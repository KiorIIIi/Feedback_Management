# Sprint 01 tests — US-010: Complete private feedback reading

State: Recorded checks passed within the cited prototype scope. This file reorganizes prior evidence; it does not claim a fresh test run on the documentation-reorganization date.

## Relevant commands

Run from the repository root with the configured PHP 8.5 executable. The following are scoped entry points; the actual recorded evidence below includes full and staged suite runs.

```powershell
php backend/vendor/phpunit/phpunit/phpunit --configuration backend/phpunit.xml --filter "FeedbackReadingTest"
npm --prefix frontend run test:e2e -- feedback-reading.spec.ts roles.spec.ts ui-bugs.spec.ts
```

## Recorded evidence

The final complete SQLite suite passed 57 tests / 417 assertions. Targeted final MySQL feedback/CSV checks passed 5 tests / 58 assertions (overlapping CSV coverage included). The full-feedback browser reading/contact/refresh/mobile test passed; role/UI regressions and public general/evaluation detail checks also passed.

## Acceptance coverage

- List and detail routes enforce the same MISO/ownership/office rules and deny personnel.
- Full messages, line breaks and optional contact fields remain readable after refresh.
- Separate 25-record pagination reaches entries older than the former 100-record limit.
- General job references grant no private service access; verified evaluations link to the authorized request.

## Sources and limits

[Iteration 1 results](../../../../changes/iteration-01/sprint-01/001-reporting-and-feedback.md#verification-record) · [Verification history](../../../../testing/verification.md) · [Implementation plan](implementation-plan/US-010-implementation-plan.md)

Reply handling, moderation, anonymous authenticated submissions and new operational permissions are excluded.
