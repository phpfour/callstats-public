## Context

The Follow-ups page is a single Inertia-rendered list backed by `FollowUpController@index` → `GetFollowUpLeadsAction@execute`. The action already selects leads needing follow-up: those whose `lastCall` outcome is in `['Missed', 'No Answer']` **OR** that have a `callback` reminder due on or before today, ordered by next reminder then name, paginated at 25.

The four queues are subsets of (or equal to) this base set:
- **All** = the existing base query, unchanged.
- **Overdue** = base ∩ (earliest callback reminder `remind_at` date < today).
- **Due today** = base ∩ (earliest callback reminder `remind_at` date = today).
- **Needs call** = base ∩ (latest call outcome ∈ `['Missed', 'No Answer']`).

The base query already exposes the pieces each queue needs: the `lastCall` relation with `outcome`, the `reminders` relation filtered to `type = callback`, and a `withMin('reminders as next_reminder_at', 'remind_at')` aggregate. No schema or relationship changes are required.

## Goals / Non-Goals

**Goals:**
- Add four queue tabs to the Follow-ups page that filter the existing list without changing its sort or pagination contract.
- Keep the `All` queue identical to today's behavior so the sidebar link and existing test remain valid.
- Validate the `queue` parameter and default to `All` on absence or invalid value.
- Keep filtering logic in the Action layer (per project conventions), not the controller.
- Show a lead count on every tab, computed for all queues independent of the active queue and of pagination.

**Non-Goals:**
- No new columns, sorting controls, or saved filters.
- No changes to what makes a lead "need follow-up" — only how the base set is sliced.
- No database or model changes.

## Decisions

### Decision: Represent queues as a PHP enum

Introduce a backed enum `App\Enums\FollowUps\FollowUpQueue` (or `App\Data\FollowUps\FollowUpQueue`) with cases `All`, `Overdue`, `DueToday`, `NeedsCall` and string values (`all`, `overdue`, `due-today`, `needs-call`) used in the URL. The enum centralizes the valid set, the default, and parsing of an incoming string.

- `FollowUpQueue::fromRequest(?string $value): self` returns the matching case or `All` when null/unrecognized — this satisfies the "unrecognized value falls back to All" requirement in one place.

**Alternatives considered:** free-form string constants in the action (rejected — scatters the valid set and default across controller and action; weaker typing).

### Decision: Apply queue filtering inside `GetFollowUpLeadsAction`

`execute()` takes the `FollowUpQueue` (default `All`) and applies an additional `where` constraint on top of the existing base query before ordering/pagination. The base "needs follow-up" predicate stays intact; each non-`All` queue adds an intersecting condition:

- **Overdue**: `whereHas('reminders', type=callback AND whereDate('remind_at','<',today()))`.
- **Due today**: `whereHas('reminders', type=callback AND whereDate('remind_at','=',today()))`.
- **Needs call**: `whereHas('lastCall', outcome IN ATTENTION_OUTCOMES)`.

Because pagination uses `withQueryString()`, the active `queue` parameter is already carried into pagination links automatically — no extra wiring needed.

**Alternatives considered:** filtering the paginated collection in PHP after fetching (rejected — breaks pagination counts and page sizes); a separate action per queue (rejected — duplicates the base query and ordering).

### Decision: Controller reads and forwards the queue; page renders tabs

`FollowUpController@index` resolves `FollowUpQueue::fromRequest($request->query('queue'))`, passes it to the action, and includes the active queue value plus the list of available queues in the Inertia props. `index.tsx` renders the tabs as links to `?queue=<value>` (the `All` tab links with no/`all` param), highlighting the active one. Tabs are plain Inertia `<Link>`s so each selection is a normal GET that re-runs the action — consistent with the existing pagination links.

**Alternatives considered:** client-side filtering of a fully-loaded dataset (rejected — defeats server pagination and scales poorly).

### Decision: Compute queue counts via a dedicated counts method on the action

Add `GetFollowUpLeadsAction::counts(): array` returning the lead count for each queue keyed by queue value. Each count reapplies the base "needs follow-up" predicate plus that queue's filter and runs a `count()` — `All` counts the base set; the three others add their intersecting condition. The counts query reuses the same base-predicate builder as `execute()` (extract it into a private method so the predicate is defined once and the two paths cannot drift).

The controller calls `counts()` and passes the map to the view alongside the paginated leads. Counts are independent of the active queue and ignore pagination by construction (they are `count()` aggregates, not page slices), satisfying the "full set, not current page" requirement.

**Alternatives considered:**
- A single grouped/conditional-aggregate query (`SUM(CASE WHEN …)`) (rejected — harder to express through Eloquent's `whereHas` relations and not worth the complexity at this scale; four indexed counts are cheap).
- Counting from the loaded page collection (rejected — only sees the current page, violating the spec).

## Risks / Trade-offs

- **"Earliest reminder" vs. "any reminder" ambiguity** → The base query already orders by `next_reminder_at` (the min `remind_at`). For Overdue/Due-today we filter on existence of a callback reminder on the matching date via `whereHas`, which is consistent with how the base set is defined and how the list is sorted. A lead with multiple reminders spanning days could match both Overdue and Due-today; this is acceptable since queues are views, not mutually exclusive buckets. Documented here so it's an explicit choice, not a bug.
- **Needs call overlap with reminder queues** → A lead can appear in both `Needs call` and a reminder queue. Intended: queues are filters over one base set, not a partition.
- **Invalid/whimsical query values** → Mitigated by `fromRequest` defaulting to `All`; no 404 or empty page from a bad param.

## Migration Plan

No data migration. Ship is purely additive: new enum, an optional parameter on the action (defaulting to `All`), controller prop changes, and front-end tabs. Rollback is reverting the change; the `All` queue preserves prior behavior so existing links and the sidebar are unaffected at every step.

## Open Questions

- None outstanding.
