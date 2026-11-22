# Event form back navigation on non-open states

Iteration 03 / Sprint 04. User feedback after trying the feature live, 20 November 2026: a generated feedback form link showed no "Back to options" navigation. The open state already renders it (inherited from the public feedback page), and the live link was re-verified to show it; the report pointed at the non-open states, which were inconsistent.

## Identity

- Iteration: 03
- Sprint: 04
- Change number and title: 002 — Event form back navigation on non-open states
- Date: 20 November 2026, Asia/Manila
- State: verified
- Request or reason: User report — "when it a generated feedback theres is no back to options".

## Changes

- `frontend/src/pages.tsx` (`EventFormPage`): the invalid/unavailable-token state and the scheduled/expired/closed state now render the same "‹ Back to options" link the open form and the general feedback page use, above their content. The closed state keeps its "Return to the portal" call to action.
- `frontend/tests/share-form.spec.ts`: the closed-state step now asserts the back link is visible.

## Verification

| Check | Actual result |
| --- | --- |
| Build (`tsc -b && vite build`) | PASS |
| `share-form.spec.ts` | PASS: 3/3, including the new back-link assertion on the closed state |
| Live app spot check | Invalid token (`/feedback/f/000…`) and the open generated link both show "‹ Back to options"; the running dev server picked the fix up via hot reload |

A stale browser tab loaded before the sprint's frontend edits may still show the pre-update bundle (including the old "Page not found" route for `/feedback/f/…`); a normal refresh resolves it.
