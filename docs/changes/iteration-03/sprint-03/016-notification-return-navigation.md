# Notification return navigation

## Change

15 November 2026, Asia/Manila. The Notifications page now provides a **Back** button. When a user opens notifications from the application navigation or a dashboard link, the destination is recorded in navigation state. Back returns to that application page, including its query string and fragment where supplied.

When Notifications is opened directly or the recorded destination is unavailable, Back goes to the signed-in role's dashboard: administrator, service personnel, or requester. Destinations are restricted to application paths, and Notifications itself is not used as a return destination.

## Scope and impact

- Applies to the administrator notification icon, requester and personnel notification navigation, and dashboard notification links.
- Preserves notification contents, read/unread behavior, authorization, and existing destinations for individual notifications.
- Does not change database schema, API behavior, or notification delivery.

## Verification and history

- The frontend TypeScript and Vite production build passed when the change was implemented.
- Dedicated browser navigation tests were not recorded for this change; the build alone does not verify every return path.
- Implementation commit: `0e6f460` — Add return navigation to notifications.

[Sprint overview](sprint-overview.md) · [Live notifications record](005-live-notifications.md)
