## 1. Model layer

- [x] 1.1 Add a `nextReminder(): HasOne` relation to `app/Models/Lead.php` using `->oldestOfMany('remind_at')` so the soonest/most-overdue reminder can be eager-loaded.

## 2. Query Action and DTO

- [x] 2.1 Create `app/Data/FollowUps/FollowUpRow.php` (final readonly DTO) shaping a row: `leadId`, `name`, `phoneNumber`, `assignedAgent` (nullable), `lastOutcome` (nullable), `lastCalledAt` (nullable), `nextReminderAt` (nullable), `lastCallId` (nullable). Add a `fromLead(Lead $lead): self` factory.
- [x] 2.2 Create `app/Actions/FollowUps/GetFollowUpLeadsAction.php` with `execute(): LengthAwarePaginator`. Build the single OR'd `Lead` query: `whereHas('lastCall', outcome IN ['Missed','No Answer'])` OR `whereHas('reminders', type='callback' AND whereDate('remind_at','<=',today()))`, eager-loading `assignedTo:id,name`, `lastCall`, and `nextReminder`.
- [x] 2.3 Apply the default sort in the Action (`nextReminder.remind_at` ascending, nulls last) and paginate (25 per page) with `withQueryString()`.
- [x] 2.4 Map paginated leads to `FollowUpRow` for the page payload (transform the paginator collection, preserving pagination meta).

## 3. Controller and route

- [x] 3.1 Create `app/Http/Controllers/Backoffice/FollowUpController.php` with a thin `index(GetFollowUpLeadsAction $action): Response` that renders `backoffice/follow-ups/index`.
- [x] 3.2 Register `Route::get('follow-ups', [FollowUpController::class, 'index'])->name('follow-ups.index')` inside the existing `role:admin|supervisor` group in `routes/web.php`.

## 4. Frontend page and navigation

- [x] 4.1 Add a "Follow-ups" nav item (Lucide icon, e.g. `ListChecks`) to the always-visible `navItems` array in `resources/js/components/backoffice-sidebar.tsx`, linking to `/backoffice/follow-ups`.
- [x] 4.2 Create `resources/js/pages/backoffice/follow-ups/index.tsx` rendering a table with columns: lead name, phone, assigned agent, last outcome + date, next reminder date. Show an empty state when there are no rows.
- [x] 4.3 Make each row clickable: link to `call-logs.show` when `lastCallId` is present, otherwise `leads.show` for that lead (use Wayfinder-generated routes).
- [x] 4.4 Handle nullable fields in the UI (no assigned agent, no next reminder date) without breaking the row.
- [x] 4.5 Run `npm run build` (or ensure dev server) so Wayfinder route types and the page compile.

## 5. Tests

- [x] 5.1 Add a feature test asserting a supervisor and an admin can load `backoffice.follow-ups.index` (200) and an agent is denied (403).
- [x] 5.2 Add tests for the call-outcome rule: lead with latest call `Missed` and `No Answer` appear; latest non-qualifying outcome does not; an older `Missed` superseded by a newer outcome does not; a lead with no calls and no due reminder does not.
- [x] 5.3 Add tests for the reminder rule: callback reminder due today and overdue appear; a future-only callback does not; a non-`callback` due reminder does not qualify.
- [x] 5.4 Add a test that a lead qualifying under both rules appears exactly once.
- [x] 5.5 Add a test asserting each row exposes name, phone, assigned agent, last outcome/date, and next reminder date, including the null-agent and no-reminder cases.
- [x] 5.6 Add a test for row navigation data: `lastCallId` points to the latest call; for a reminder-only lead with no calls, `lastCallId` is null so the row targets the lead detail page.

## 6. Finalize

- [x] 6.1 Run `vendor/bin/pint --dirty --format agent` to format changed PHP.
- [x] 6.2 Run `php artisan test --compact --filter=FollowUp` and confirm all new tests pass.
