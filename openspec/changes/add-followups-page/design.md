## Context

The backoffice already exposes Leads, Call Logs, Reports, and Leaderboard pages under a single route group guarded by `auth, verified, role:admin|supervisor` (`routes/web.php`). The domain models needed already exist:

- `Lead` has `assignedTo`, `callLogs`, `reminders`, and a `lastCall` relation (`hasOne(CallLog)->latestOfMany('called_at')`).
- `CallLog.outcome` is a free-text string constrained to `config('call.outcomes')`; the relevant values are `Missed` and `No Answer`.
- `Reminder` has `remind_at` (datetime cast) and a `type` string; callback reminders use `type = 'callback'` (see `CallLog::callbackReminder()`).

The codebase convention (CLAUDE.md) is Action classes + DTOs: controllers stay thin, build a filters/data object, and delegate to an Action. `ListLeadsAction` is the closest precedent for a read/query Action returning data to an Inertia page.

No new tables, columns, or dependencies are required.

## Goals / Non-Goals

**Goals:**
- Surface the set of leads needing attention in one backoffice page, using the two qualifying rules from the spec, with each lead listed once.
- Reuse existing routing, authorization, Action/DTO, and Inertia page conventions.
- Make each row click-through to the latest call log detail (existing `call-logs.show`), falling back to the lead detail page.

**Non-Goals:**
- No new "Follow-up" workflow state, snooze, dismiss, or completion tracking on reminders or leads.
- No new call-log or lead detail page — those routes already exist and are reused as-is.
- No notifications, scheduling, or background jobs.
- No changes to agent-facing surfaces; this is supervisor/admin only.

## Decisions

### Decision: One OR'd query on `Lead`, not a UNION or in-memory merge

Build a single Eloquent query on `Lead` that returns a lead when **either** rule matches, using `whereHas` OR'd together:

```php
Lead::query()
    ->where(function ($q) {
        $q->whereHas('lastCall', fn ($c) => $c->whereIn('outcome', ['Missed', 'No Answer']))
          ->orWhereHas('reminders', fn ($r) =>
              $r->where('type', 'callback')->whereDate('remind_at', '<=', today())
          );
    })
    ->with(['assignedTo:id,name', 'lastCall', 'nextReminder'])
    ->paginate(25);
```

- `whereHas('lastCall', …)` correctly constrains on the *latest* call only, because `lastCall` already carries the `latestOfMany('called_at')` subquery — so an older missed call superseded by a newer outcome does not qualify (spec scenario "An earlier call was missed but the latest was not").
- OR'ing in a single `Lead` query guarantees each lead appears exactly once (spec "Each lead appears once") without manual dedup.

**Alternatives considered:** (a) Two separate queries merged in PHP — requires manual de-duplication and breaks pagination. (b) SQL `UNION` — harder to paginate/sort and to eager-load relations. The single OR'd query is simpler and paginates cleanly.

### Decision: "Due on or before today" = `whereDate('remind_at', '<=', today())`

Compare on the date, so any reminder timestamped anywhere within today (or earlier) qualifies, matching "due today or overdue" and excluding strictly-future reminders. The callback filter also pins `type = 'callback'` so non-callback reminders never qualify a lead.

### Decision: Add a `nextReminder` relation for the displayed reminder date

Display "next reminder date" via a dedicated `Lead::nextReminder()` HasOne ordered by `remind_at` ascending (the soonest/most-overdue reminder), eager-loaded to avoid N+1:

```php
public function nextReminder(): HasOne
{
    return $this->hasOne(Reminder::class)->oldestOfMany('remind_at');
}
```

A lead that qualifies only by call outcome may have no reminder, so the column is nullable (spec "Lead qualifies only by call outcome with no reminder"). Using a relation keeps it eager-loadable and consistent with `lastCall`.

**Alternative considered:** computing the date from the already-loaded `reminders` collection — rejected to avoid loading every reminder per lead.

### Decision: `GetFollowUpLeadsAction` + `FollowUpController` + DTO row shape

Follow the established pattern:
- `App\Actions\FollowUps\GetFollowUpLeadsAction::execute(): LengthAwarePaginator` — owns the query above.
- `App\Http\Controllers\Backoffice\FollowUpController::index()` — thin; calls the Action and `Inertia::render('backoffice/follow-ups/index', …)`.
- A `FollowUpRow` DTO (`App\Data\FollowUps\FollowUpRow`) shapes each row to exactly the fields the page needs: `leadId`, `name`, `phoneNumber`, `assignedAgent`, `lastOutcome`, `lastCalledAt`, `nextReminderAt`, plus `lastCallId` (for the row link target). This keeps the page payload explicit and avoids leaking whole models.

Route: `Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index')` inside the existing `role:admin|supervisor` group — authorization is inherited, so the "agent denied" scenario is covered by the existing middleware with no extra policy.

### Decision: Row navigation resolved on the server via `lastCallId`

The page links each row to `call-logs.show` when `lastCallId` is present, else to `leads.show` for that lead (spec "Row links to call log detail"). The decision lives in the row data (`lastCallId` nullable) so the React page only branches on a single field; routes are generated with Wayfinder.

### Decision: Sidebar entry

Add a "Follow-ups" item to `resources/js/components/backoffice-sidebar.tsx` in the always-visible `navItems` array (admin + supervisor), using a Lucide icon (e.g. `ListChecks`/`PhoneForwarded`), linking to `/backoffice/follow-ups`.

## Risks / Trade-offs

- **Reminders have no completion state** → A callback reminder with `remind_at` in the past stays "due" forever, so a lead can linger on the list after it has been actioned. Mitigation: out of scope for this change; documented as a known limitation. A future change can add a `completed_at`/status column and filter on it. Surfaced as an open question below.
- **`whereHas` performance on large lead/call volumes** → correlated subqueries over `call_logs` and `reminders`. Mitigation: existing indexes on `call_logs.lead_id` and `reminders.lead_id`; pagination caps result size. Revisit with a composite index if the page slows.
- **Outcome strings are not enum-backed** → filtering on literal `'Missed'`/`'No Answer'` couples the query to config values. Mitigation: reference the same strings the rest of the app uses (Dashboard already hard-codes them); acceptable given current design.

## Migration Plan

No database migration. Deploy is additive: new route, controller, action, DTO, Inertia page, and one sidebar item. Rollback = revert the change; nothing persists and no existing behavior is modified.

## Open Questions

- Should "due" reminders be cleared once acted on (i.e., does this change need a reminder completion/status concept), or is showing all past-due callbacks acceptable for v1? Current design assumes the latter.
- Should the list have a default sort (e.g., most overdue reminder first, then oldest missed call)? Proposed default: `nextReminder.remind_at` ascending, nulls last — to confirm during tasks/implementation.
