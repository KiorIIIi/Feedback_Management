# 003 — Development branch workflow

Date: 21 October 2026, Asia/Manila.

The user authorized committing and pushing completed project changes to the existing development branch until the final version is approved for merge. They requested correcting `fornt-main` to **`front-main`**.

The branch was renamed through GitHub's branch-rename API, preserving its existing commit history. Local tracking is updated to `origin/front-main`. The canonical continuing instructions are in [Development workflow](../../../project/development-workflow.md), linked from the documentation index.

`main` remains the final integration target; no merge or push to `main` is authorized by this request. Publishing the development branch is separate from deploying the application.

The user authorized publishing the completed feedback revamp, shared badge refinement and associated documentation. Their test results and known welcome-illustration sizing failure remain recorded in [001](001-feedback-ui-revamp.md) and [002](002-borderless-status-labels.md). Personal IDE files, runtime data, secrets and generated test artifacts stay local.

[Sprint overview](sprint-overview.md) · [Development branch](https://github.com/KiorIIIi/Feedback_Management/tree/front-main)
