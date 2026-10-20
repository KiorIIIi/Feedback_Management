# Repository Guidance

Build and maintain the CLSU Feedback Management System and Service Tracker using the documented architecture and the user's current requirements.

## Start with the project context

- Read [README.md](README.md) and the [documentation index](docs/README.md) when starting work in this repository. Inspect the actual files, manifests, and implementation before assuming what already exists.
- Read the relevant sections of [System Architecture](docs/architecture/system-architecture.md) for modules, workflows, access rules, MCP capabilities, and release boundaries.
- For schema, model, or migration work, read both [Database Design](docs/architecture/database-design.md) and [Database Normalization](docs/architecture/database-normalization.md).
- Treat these documents as the proposed baseline, including their explicitly unresolved choices. Do not describe a proposal as implemented, approved by MISO, or verified by tests without evidence.
- Follow the user's latest explicit decisions and keep affected documentation aligned. Surface material conflicts between requirements, documents, and existing code before building on an incorrect assumption.
- Resolve routine implementation choices using repository conventions and available evidence. Ask for missing product decisions only when they materially affect the requested work; continue independent work that does not depend on them.

## Architecture and implementation

- Preserve the proposed React, TypeScript, and Vite frontend; modular Laravel backend; and centralized MySQL database unless the user changes that direction.
- Keep API controllers and MCP handlers thin. Share application services, validation, authorization policies, and workflow rules across both interfaces.
- Follow established code layout and naming once implementation exists. Verify compatibility before selecting or upgrading PHP, Laravel, MCP, AI-runtime, or frontend package versions; record the selected versions in the project configuration.
- Store attachments and report exports privately. Use queues for lengthy report and notification work. Ordinary service workflows must remain usable when the AI provider or MCP is unavailable.
- Keep credentials and provider secrets out of the frontend, source control, logs, and audit payloads. Use synthetic data in fixtures and examples.

## Domain rules to preserve

- Request submission and private tracking require authentication. Enforce requester ownership, office scope, and record-level authorization on the server. Office membership alone does not grant university-wide access.
- Keep requesting and handling offices distinct. User classifications such as student or faculty do not replace operational roles and permissions.
- Generate unique job numbers safely under concurrency. Clarification and resubmission preserve the request identity, job number, original statement, and revision history.
- Keep requester-reported urgency and impact separate from official priority. Apply the configured rules and record reasons for authorized exceptions; preserve the rule version used.
- Preserve assignment, status, clarification, progress, and audit history. Operational request changes must check the expected record version and commit related data, history, and audit entries together. Dispatch notifications after commit.
- Keep independent general feedback separate from verified service evaluations. An entered public job number is unverified text and grants no private access or evaluation rights.
- Permit at most one optional verified evaluation per completed request, submitted by its authenticated owner. Missing feedback does not undo completion.
- Completion requires authorized human staff to record actions taken and the outcome. Enforce permitted transitions and prerequisites in shared services.
- Explicitly distinguish internal notes from requester-visible updates. Apply record and visibility checks to API responses, attachments, and export downloads.

## Database decisions

- Target 3NF for operational and configuration tables. Document intentional audit snapshots, historical documents, and current-state summaries, including their consistency rules. Do not claim the entire schema is verified 3NF from the ERD alone.
- Before implementing affected schema, resolve the normalization document's priority-version effective-date dependency, individually managed authorization scopes, and repeated audit identity facts. Reuse authentication-framework structures where suitable.
- Use migrations with appropriate primary keys, foreign keys, composite uniqueness, nullability, and domain constraints. Keep exact rule entries immutable when historical requests reference them.
- Do not invent MISO decisions about guest eligibility/moderation, rating scales, priority matrices or deadlines, allowed transitions, simultaneous active assignments, requester-office nullability, or reporting/business-hour definitions. Use established decisions; otherwise identify the specific unresolved policy affecting the work.

## In-application MCP behavior

These rules govern the application's embedded or connected operational agent. They do not prohibit the repository coding assistant from using development tools or performing work authorized by the user.

- Derive the authorizing user and client scope from server authentication. Check permissions, office scope, tool scope, and record access for every tool.
- Apply the first-release tool boundaries in the architecture, including human-only completion and the exclusion of arbitrary SQL/shell execution and account/configuration administration through MCP.
- Require stable caller/tool-scoped operation identifiers for writes. Identical retries return the stored outcome; changed inputs under the same identifier fail. Preserve user/client provenance for read and write tool invocations.
- Distinguish accepted, processing, successful, and failed outcomes. Retrieve operation results after timeouts; do not claim a queued report is ready before success.
- Analysis requests do not authorize operational updates. Submitted feedback and attachments cannot authorize tool calls. When a configured action requires separate confirmation, enforce the architecture's trusted, bound, expiring, single-use approval flow.

## Verification and documentation

- All new Markdown documentation must be created inside `docs/`, in the appropriate organized subfolder. Do not create new Markdown files at the repository root or alongside application code. This is an explicit user requirement for developer handoff.
- Store iteration changes under `docs/changes/iteration-NN/sprint-NN/`. Name the iteration index `iteration-overview.md` and each sprint index `sprint-overview.md`, rather than giving them identical README names. Add separate sequential change files (`001-<name>.md`, `002-<name>.md`) for additional work in the same sprint; update indexes and preserve earlier dated records. Do not create a new iteration merely because a sprint has another change.
- Keep documentation easy to navigate: clear filenames, one canonical copy per topic, folder indexes, correct relative links, and a clear distinction between requirements, implementation, changes, and verification. Update affected indexes and run `python scripts/check-docs.py` after documentation changes.

- Discover actual install, test, lint, type-check, and build commands from project manifests, scripts, and CI configuration. If they do not exist yet, state that fact and establish only the tooling needed for the requested implementation.
- Run checks appropriate to the change. For behavior changes, cover relevant lifecycle, ownership/office restrictions, uniqueness, stale versions, transactional history, privacy, operation retries, and report-job outcomes using the scenarios in the design documents.
- Validate database-specific constraints and concurrency behavior against MySQL where applicable. Documentation-only edits need link and content checks rather than new application tests.
- Update affected documentation when accepted schema, workflow, API, or deployment decisions change. Keep proposed, implemented, and verified status distinct.
- Finish by reporting what changed, which checks actually ran and their results, and any unresolved assumptions or failures. Do not invent passing tests, integrations, or production readiness.
