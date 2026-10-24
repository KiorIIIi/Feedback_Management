# Automatic workspace updates

Implemented 24 October 2026. The portal checks for changes about every five seconds while its tab is visible and online, using the existing authenticated HTTP API. Network/response time is additional. No WebSocket server, AI provider, browser notification permission or new dependency is required.

## Updated behavior

- Notification badges and the notifications page share one feed per workspace for all three account roles. A new notice produces one dismissible in-app alert with a server-generated record link. Existing unread notices do not replay on sign-in or navigation.
- New requests notify active handling-office administrators; independent feedback notifies active MISO administrators; verified evaluations notify handling-office administrators separately. The submitting administrator is excluded from their own staff alert. Existing requester receipt/progress and personnel assignment notices remain. Internal-only notes do not become requester alerts.
- Request lists, dashboards, feedback lists/details, personnel workload and report summaries refresh without loading flashes or resetting filters/pagination. No historical notification backfill, sound, operating-system push or outbound email is added.

## Editing and connection recovery

Typing in any request management or clarification form pauses detail replacement, including a background read already in flight. The original record version stays with the draft. A stale save receives the existing 409 response and keeps the draft visible. **Load latest request** explicitly clears it and loads current state for review. Successful saves resume updates.

Hidden/offline tabs skip background reads. Focus, visibility restoration, reconnecting and successful same-tab mutations trigger a check. Each resource permits one read at a time, cancels on unmount, and has a 15-second timeout. Temporary failures retain loaded content and retry after 10, 20 and at most 30 seconds; the shared feed shows a reconnecting message. Authentication failures use normal session expiry; forbidden/not-found reads clear their prior data.

This is periodic synchronization, not instantaneous delivery or a capacity certification. Existing read throttles apply. Large deployments may need broadcasting or more selective invalidation after measurement.

## API and authorization

`GET /api/notifications?feed=1` returns `{notifications, unread_count}`: the latest 100 authorized notices plus the unread total across all authorized notices. Without `feed=1`, the existing array shape is retained. The frontend also accepts that older shape during rollout. Responses use private/no-store caching.

Feed reads and `POST /api/notifications/{id}/read` check the current account and record/office access. Losing assignment or handling-office access hides the associated notices and prevents acknowledgement. General feedback shares its existing MISO/owner scope; personnel cannot receive evaluation alerts. Links derive from typed foreign keys, never submitted text or unverified public job numbers.

In-app notification rows are inserted within the submission/workflow transaction with the record and audit history. Readers see them after commit; a rollback removes the related inserts. No external delivery is dispatched before commit.

## Schema and rollout

Additive migration `2026_10_24_000400_extend_portal_notifications` adds a constrained `kind` (`request`, `feedback`, `evaluation`), nullable `general_feedback_id` foreign key and an inbox index on user/read-state/ID. Existing rows default to request notices. Existing nullable `service_request_id` identifies request/evaluation targets. Writers choose the appropriate target; readers exclude mismatched/dual-target rows. No original submission/history is rewritten.

Run `php backend/artisan migrate` on an existing development installation; use the updated frontend with the migrated backend. Never reseed a populated database. Separately authorized public-demo rollout uses its existing migration/build/restart procedure; a commit alone does not republish that artifact.

[Change and verification](../changes/iteration-03/sprint-03/005-live-notifications.md) · [Implementation notes](implementation-notes.md) · [Testing](../testing/README.md)
