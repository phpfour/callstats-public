## Why

Supervisors and admins have no single place to see which leads are slipping through the cracks. Missed and unanswered calls, plus due callback reminders, are scattered across the leads list, call logs, and individual reminders — so leads that need a prompt follow-up get forgotten. A dedicated Follow-ups page surfaces exactly the leads that need attention right now.

## What Changes

- Add a **Follow-ups** page to the backoffice, reachable from a new sidebar entry, visible to supervisors and admins.
- The page lists leads that need attention, where a lead qualifies when **either**:
  - its latest call outcome is `Missed` or `No Answer`, **or**
  - it has a callback reminder due on or before today.
- Each row shows: lead name, phone number, assigned agent, last call outcome and date, and next reminder date.
- Clicking a row opens the call log detail page for the lead's latest call (or the lead detail page when the lead has no calls).
- Add a backoffice route + controller that builds this list (an Action/DTO encapsulating the attention query).

## Capabilities

### New Capabilities
- `lead-followups`: Identifying and listing leads that need follow-up attention (missed/no-answer last call, or a callback reminder due today or earlier), with the per-row detail shown to supervisors and admins.

### Modified Capabilities
<!-- None — no existing spec requirements change. -->

## Impact

- **Routes**: new `GET /backoffice/follow-ups` (`backoffice.follow-ups.index`) inside the existing `auth, verified, role:admin|supervisor` group in `routes/web.php`.
- **Controllers/Actions**: new `Backoffice\FollowUpController` plus an Action (e.g. `GetLeadsNeedingFollowUpAction`) and supporting DTO under `app/Actions/FollowUps/` and `app/Data/FollowUps/`.
- **Models**: reuses `Lead` (`lastCall`, `assignedTo`, `reminders`), `CallLog.outcome`, and `Reminder` (`remind_at`, `type = 'callback'`); no schema changes expected.
- **Frontend**: new Inertia page `resources/js/pages/backoffice/follow-ups/index.tsx`; new nav item in `resources/js/components/backoffice-sidebar.tsx`.
- **Config**: references the `Missed` / `No Answer` outcome values from `config/call.php`.
