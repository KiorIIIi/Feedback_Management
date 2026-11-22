# GitHub Actions test fixes

22 November 2026, Asia/Manila.

The [verification run for `dbdcd7e`](https://github.com/KiorIIIi/Feedback_Management/actions/runs/45021161461) exposed two issues in the test suite.

- The event feedback test assumed MySQL returned JSON object keys in insertion order. The test now sorts the decoded audit payload keys before strictly comparing both IDs and their types.
- The administrator browser test navigated to a legacy login URL immediately after clicking Sign out. It now waits for the app to return to the welcome page before opening the login URL, then continues to verify the administrator workspace and session persistence.

Local verification passed all four feedback-form tests on isolated SQLite and all nine role-login checks across three Chrome runs. The documentation link check passed.

[Sprint overview](sprint-overview.md)
