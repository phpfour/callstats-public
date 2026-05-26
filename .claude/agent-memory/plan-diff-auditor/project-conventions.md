---
name: project-conventions
description: Recurring conventions and drift patterns observed in callstats plan-diff reviews
metadata:
  type: project
---

## Plan structure
plan.md files in this project are highly detailed: numbered phases, per-file change specs, query strategy, test plan with exact test bullets, and a task checklist. Reviews should verify every named test bullet individually.

## Default base branch
`main` is the correct base branch.

## Recurring drift categories

### CONVERSION_OUTCOMES: config vs private const
Plan §5 specifies `private const CONVERSION_OUTCOMES` inside `ShowAgentDetailAction` as a "single source of truth." Implementation moved this to `config/call.php` under the key `conversion_outcomes` and reads it via `config()`. This is a deliberate architectural divergence — improves configurability but deviates from plan wording. Flag this pattern in future reviews.

### SyncAgentKpiTargetAction upsert condition
Plan §4 stated: "if role is `agent` and **either target is non-null** → updateOrCreate." Implementation always upserts when role is agent (even when both targets are null). This changes semantics: an agent with no targets set still creates a row, whereas the plan implied no row unless at least one target is provided. The StoreTest actually asserts the row IS created with null targets ("creates a KPI row with null targets when none are supplied"), so the test and implementation agree — but both diverge from plan §4 wording.

### UserRequest conditional validation
Plan §3 says KPI rules apply "only validated when `role === 'agent'`." Implementation applies rules unconditionally (no `sometimes`, no `required_if`). Since rules are `nullable`, a non-agent submitting KPI values will pass validation silently rather than being rejected — behavior matches plan intent but implementation mechanism differs.

### Unplanned files
- `brief.md` — high-level acceptance criteria document added alongside `plan.md`. Not in plan.
- `config/call.php` (`conversion_outcomes` key) — not in plan; plan called for a private const.
- `resources/js/components/app-logo.tsx` — unrelated UI change (branding from hardcoded "Laravel Starter Kit" to dynamic `{name}` prop). Not in plan.
- `resources/js/components/charts/outcome-breakdown.tsx` — plan did not specify an Outcome donut chart; it was an extra feature added to the dashboard.
- `resources/js/components/charts/index.ts` — updated to export the unplanned outcome-breakdown chart.

**Why:** The dashboard commit (`c588873`) bundled unplanned dashboard visualizations (outcome breakdown donut + top agents leaderboard) into this branch. These were not in plan.md.

## Test coverage gap pattern
The plan's §6 bullet "hides progress bar (payload flag) when corresponding target is null" was interpreted as checking `agent.targets.*` being null, not a dedicated boolean payload flag. The test names expose this: "exposes nulls in the targets payload" rather than "hides progress bar payload flag." Acceptable interpretation but note the plan wording implied a boolean flag.

## Legitimate side effects
- `database/factories/CallLogFactory.php` — adding `'Interested'` and `'Call Back Requested'` to outcomes list is plan task #12.
- `database/factories/UserFactory.php` — `withKpiTargets()` state is plan task #9.
