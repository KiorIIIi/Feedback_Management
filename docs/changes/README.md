# Changes and iterations

| Iteration | Scope |
| --- | --- |
| [Iteration 01](iteration-01/iteration-overview.md) | Reporting, categories, full feedback review and subsequent documentation changes, grouped by sprint |
| [Iteration 02](iteration-02/iteration-overview.md) | Reviewed Figma Option A feedback revamp, working filters, authorized service context and runtime/development support; Sprint 02 |
| [Iteration 03](iteration-03/iteration-overview.md) | Implementation of major feature — authenticated MCP read foundation and Claude/ChatGPT/Gemini connection guide; Sprint 03 |

Every iteration has its own `iteration-NN/` folder. Its `iteration-overview.md` indexes the sprint folders; each sprint's `sprint-overview.md` indexes its separate change records. Add another numbered change file when more work happens in the same sprint. Start a new iteration folder only when a new iteration cycle is identified.

```text
docs/changes/
  iteration-01/
    iteration-overview.md
    sprint-01/
      sprint-overview.md
      001-reporting-and-feedback.md
      002-documentation-reorganization.md
      003-iteration-folder-layout.md
      004-overview-file-names.md
```

Use the [change-record template](_TEMPLATE.md). Number files sequentially within their sprint (`001-…`, `002-…`). Update the sprint index when adding a change and the iteration index when adding a sprint. Keep earlier records and their dated evidence; do not overwrite them or label an unrun check as passed.

Agile implementation plans and story verification stay under [agile](../agile/README.md) and link to the relevant change record. Detailed implementation decisions remain under [project](../project/README.md), and shared test history under [testing](../testing/README.md).
