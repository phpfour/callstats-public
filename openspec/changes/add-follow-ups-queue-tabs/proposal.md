## Why

The Follow-ups page currently shows every lead needing attention in one undifferentiated list, so agents cannot tell which leads are overdue, due today, or stuck on a bad last call. Splitting the list into named queues lets agents prioritize their day without manually scanning dates and outcomes.

## What Changes

- Add queue tabs to the backoffice Follow-ups page that filter the existing leads list:
  - **All** — every lead needing follow-up (current behavior, default queue).
  - **Overdue** — leads whose earliest callback reminder is due *before* today.
  - **Due today** — leads whose earliest callback reminder is due *today*.
  - **Needs call** — leads whose latest call outcome is `Missed` or `No Answer`.
- Show a count next to each tab's label indicating how many leads fall in that queue (computed for all queues regardless of which one is active).
- Accept a `queue` query parameter on the Follow-ups index route; default to `All` when absent or invalid.
- Apply the selected queue as a filter on the existing leads query while preserving current sorting and pagination (pagination links carry the active `queue`).
- Surface the active queue and the route to the React page so tabs render the correct selection.
- The sidebar link continues to point at the Follow-ups index with no `queue` parameter, which resolves to the default **All** queue.

## Capabilities

### New Capabilities
- `follow-up-queues`: Defines the queue tabs on the Follow-ups page — the set of queues, their filtering rules, the default, and how queue selection interacts with sorting and pagination.

### Modified Capabilities
<!-- No existing specs in openspec/specs/; the Follow-ups page has no prior spec to modify. -->

## Impact

- `app/Http/Controllers/Backoffice/FollowUpController.php` — read and validate the `queue` parameter, pass it to the action and the view.
- `app/Actions/FollowUps/GetFollowUpLeadsAction.php` — apply queue-specific filtering on top of the base "needs follow-up" query.
- `app/Data/FollowUps/` — likely a new value/enum to represent the queue set and its rules.
- `resources/js/pages/backoffice/follow-ups/index.tsx` — render the queue tabs and reflect the active queue.
- `tests/Feature/Backoffice/FollowUps/IndexTest.php` — cover each queue's filtering and the default.
- No database schema, dependency, or sidebar route changes.
