# Development workflow

User instruction recorded 21 October 2026, Asia/Manila.

The active development branch is **`front-main`**, tracking **`origin/front-main`** in [Feedback_Management](https://github.com/KiorIIIi/Feedback_Management/tree/front-main). The user requested correcting the original `fornt-main` spelling.

- Continue the current UI experiment and subsequent development cycles on `front-main` until the user changes this instruction or approves the final merge.
- Commit and push completed, verified project changes to `origin/front-main`. Report actual checks and any remaining failures; do not imply all tests passed when they did not.
- Keep `main` as the final integration target. Do not merge into or push development changes directly to `main` without the user's approval of the final version.
- Fetch current remote references before deciding that a branch is absent. Preserve existing local work when switching branches; inspect and resolve divergence rather than force-pushing.
- Keep local runtime data, credentials, generated test artifacts and personal IDE workspace files out of commits.
- Record changes under the current iteration/sprint in `docs/changes/`; preserve prior records and update indexes.

Pushing a branch is separate from deploying the running system. Deployments require their own task authorization.

[Project guidance](README.md) · [Iteration 02](../changes/iteration-02/iteration-overview.md)
