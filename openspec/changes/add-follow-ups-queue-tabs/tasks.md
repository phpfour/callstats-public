## 1. Queue enum

- [x] 1.1 Create `App\Data\FollowUps\FollowUpQueue` backed enum with cases `All` (`all`), `Overdue` (`overdue`), `DueToday` (`due-today`), `NeedsCall` (`needs-call`)
- [x] 1.2 Add `FollowUpQueue::fromRequest(?string $value): self` that returns the matching case or `All` for null/unrecognized values
- [x] 1.3 Add a helper exposing the queue's label and value for the front-end (e.g. `label()` and an array of all queues for tab rendering)

## 2. Action filtering

- [x] 2.1 Update `GetFollowUpLeadsAction::execute()` to accept a `FollowUpQueue $queue = FollowUpQueue::All` parameter
- [x] 2.2 Apply the queue filter on top of the base "needs follow-up" query: Overdue → callback reminder `remind_at` date `<` today; Due today → date `=` today; Needs call → `lastCall.outcome` in `Missed`/`No Answer`; All → no extra filter
- [x] 2.3 Keep existing ordering, `withQueryString()`, and pagination so the active queue carries into pagination links
- [x] 2.4 Extract the shared base "needs follow-up" predicate into a private method so `execute()` and counts reuse one definition
- [x] 2.5 Add `GetFollowUpLeadsAction::counts(): array` returning each queue's `count()` (base predicate + queue filter) keyed by queue value

## 3. Controller & props

- [x] 3.1 Resolve `FollowUpQueue::fromRequest($request->query('queue'))` in `FollowUpController@index` and pass it to the action
- [x] 3.2 Pass the active queue value, the list of available queues (value + label), and the per-queue counts to the Inertia view

## 4. Front-end tabs

- [x] 4.1 Render the four queue tabs in `resources/js/pages/backoffice/follow-ups/index.tsx` as Inertia `<Link>`s to `?queue=<value>` (All links with no/`all` param)
- [x] 4.2 Highlight the active queue from props and keep the existing table, empty state, and pagination intact
- [x] 4.3 Show each queue's count next to its label using the counts prop

## 5. Tests

- [x] 5.1 Update/extend `tests/Feature/Backoffice/FollowUps/IndexTest.php`: default (no queue) shows the All set and is unchanged
- [x] 5.2 Assert Overdue shows only leads with a callback reminder due before today
- [x] 5.3 Assert Due today shows only leads with a callback reminder due today
- [x] 5.4 Assert Needs call shows only leads whose latest call outcome is `Missed`/`No Answer`
- [x] 5.5 Assert an unrecognized `queue` value falls back to the All queue
- [x] 5.6 Assert pagination links retain the active queue parameter
- [x] 5.7 Assert each tab's count reflects the full matching set (independent of active queue and across pages), with 0 for an empty queue

## 6. Finalize

- [x] 6.1 Run `vendor/bin/pint --dirty --format agent`
- [x] 6.2 Run `php artisan test --compact --filter=FollowUps` and ensure green
