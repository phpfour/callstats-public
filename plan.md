# Agent detail page with configurable KPI targets — Plan

## 1. Intent
Let admins set per-agent KPI targets (daily calls, conversion %) — stored in a dedicated table — on the user edit page, and open a per-agent detail page from the dashboard that shows today's actuals against those targets as progress bars alongside last-30-days stats and recent calls.

## 2. Schema design
**New table `agent_kpi_targets`** (one row per agent, created lazily):
- `id` — pk
- `user_id` — `foreignId().constrained('users').cascadeOnDelete().unique()` (one row per user; uniqueness enforces 1:1)
- `daily_call_target` — `unsignedSmallInteger().nullable()` (matches "integer ≥ 0")
- `conversion_rate_target` — `unsignedTinyInteger().nullable()` (0–100 fits)
- `timestamps`

**Why a separate table:** keeps `users` clean of role-specific columns; auto-clears via FK when a user is deleted; lets us drop the row when a user changes away from `agent` role rather than carrying stale columns.

**No CallLog change.** Today's actuals are computed on the fly from `call_logs`.

**Conversion definition:** outcome ∈ `['Successful Contact', 'Follow-up', 'Interested', 'Call Back Requested']`. Centralized as a private const in the action that uses it so it's a single source of truth.

## 3. Existing files to modify
- `app/Models/User.php` — add `kpiTarget(): HasOne` relation to the new model.
- `app/Http/Requests/Backoffice/UserRequest.php` — add conditional rules: `integer|min:0` for `daily_call_target`, `integer|between:0,100` for `conversion_rate_target`; both nullable; only validated when `role === 'agent'`.
- `app/Data/Users/StoreUserData.php` — add `?int $dailyCallTarget`, `?int $conversionRateTarget`; `fromRequest` forces both to `null` if role ≠ agent.
- `app/Actions/Users/StoreUserAction.php` — after `syncRoles`, call `SyncAgentKpiTargetAction` so it also handles the role-switch case in updates.
- `app/Actions/Users/UpdateUserAction.php` — same call to `SyncAgentKpiTargetAction` after `syncRoles`.
- `app/Http/Controllers/Backoffice/UserController.php@edit` — eager-load `kpiTarget`, expose its values flat on the `user` payload.
- `resources/js/pages/backoffice/users/user-form.tsx` — new "KPI targets" section, conditionally rendered when `form.data.role === 'agent'`.
- `resources/js/pages/backoffice/users/create.tsx` & `edit.tsx` — extend `UserFormShape` and pass initial values.
- `resources/js/components/top-agents-list.tsx` — wrap agent name in a `<Link>` to `/backoffice/agents/{id}`; requires `id` in the row.
- `app/Services/AnalyticsService.php@getTopAgentsThisWeek` — include `users.id` in the result row; update the PHPDoc.
- `resources/js/pages/backoffice/dashboard.tsx` — update `TopAgentRow` type with `id`.
- `routes/web.php` — add `GET backoffice/agents/{agent}` → `AgentController@show` inside the existing `auth|verified|role:admin|supervisor` group.
- `database/factories/UserFactory.php` — add a `withKpiTargets(?int $calls, ?int $rate)` state that creates an `AgentKpiTarget` after the user.

## 4. New files to create
- `database/migrations/2026_05_22_120000_create_agent_kpi_targets_table.php`
- `app/Models/AgentKpiTarget.php` — `$fillable = ['user_id', 'daily_call_target', 'conversion_rate_target']`; casts both to int; `belongsTo(User::class)`.
- `database/factories/AgentKpiTargetFactory.php`
- `app/Actions/Users/SyncAgentKpiTargetAction.php` — given `User` + `StoreUserData`: if role is `agent` and either target is non-null → `updateOrCreate` on `user_id`; if role is not `agent` → delete any existing row.
- `app/Http/Controllers/Backoffice/AgentController.php` — `show(User $agent)`.
- `app/Actions/Agents/ShowAgentDetailAction.php` — assembles the DTO described below.
- `app/Data/Agents/AgentDetail.php` — typed response shape.
- `resources/js/pages/backoffice/agents/show.tsx` — three sections: today vs targets (progress bars), 30-day stat cards, recent-calls `DataTable`.
- `resources/js/components/kpi-progress-bar.tsx`
- Tests (see §6).

## 5. Query strategy
**Single `ShowAgentDetailAction::execute(User $agent)` returning one DTO.** Three queries:

1. **Today's actuals** — one `selectRaw` over `call_logs` where `user_id = $agent->id` and `DATE(called_at) = today`:
   - `COUNT(*) as calls_today`
   - `SUM(CASE WHEN outcome IN (…CONVERSION_OUTCOMES) THEN 1 ELSE 0 END) as conversions_today`
   - Compute `conversion_rate_today = conversions / calls * 100` in PHP (skip the SQL NULLIF).
2. **Last-30-days stats** — one `selectRaw` over same table where `called_at >= today - 29 days`:
   - `COUNT(*) as total`, `SUM(duration) as talk_time`, `SUM(CASE … conversion outcomes …) as conversions`, `AVG(duration) as avg_duration`.
3. **Recent calls** — `CallLog::where('user_id', $agent->id)->with('lead:id,name,phone_number')->latest('called_at')->limit(25)->get()`.

`AgentController@show` 404s unless `$agent->hasRole('agent')`. KPI targets come from `$agent->kpiTarget` (may be null); page hides any bar whose target is null.

**Conversion outcomes** live as a single `private const CONVERSION_OUTCOMES` in `ShowAgentDetailAction`. (If the project later adds a `CallOutcome` enum, this is the one place to swap.)

## 6. Test plan
**Feature (Pest):**
- `tests/Feature/Backoffice/Users/StoreTest.php` & `UpdateTest.php` — extend:
  - persists KPI targets to `agent_kpi_targets` when role is agent.
  - rejects negative `daily_call_target`.
  - rejects `conversion_rate_target` outside 0–100.
  - role-switch from agent → supervisor deletes the existing `agent_kpi_targets` row.
  - non-agent role + KPI input → no row created.
- `tests/Feature/Backoffice/Agents/ShowTest.php` (new):
  - 404 when target user is not an agent.
  - 403 for non-admin/supervisor (per existing middleware).
  - returns today's call count and conversion rate (each conversion-outcome value counted).
  - returns last-30-days totals.
  - returns up to 25 most-recent call logs ordered by `called_at DESC`.
  - hides progress bar (payload flag) when corresponding target is null.
- `tests/Feature/DashboardTest.php` — assert `topAgents[].id` is now present.

After implementation: `php artisan test --compact --filter='Users|Agents|Dashboard'` and `vendor/bin/pint --dirty --format agent`.

## 7. Risks and assumptions
- **Conversion outcomes:** confirmed as `['Successful Contact', 'Follow-up', 'Interested', 'Call Back Requested']`. Note: `'Interested'` and `'Call Back Requested'` aren't in `CallLogFactory` today — the factory should add them so tests can produce conversions deterministically. Updated as part of Phase 2.
- **URL space:** mounting at `/backoffice/agents/{agent}` (not root `/agents/...`) to stay consistent with the rest of the backoffice.
- **Authorization:** supervisors can view the detail page (existing middleware). Editing KPI targets stays admin-only via the existing users route group.
- **30-day stats shape:** picking total calls, total talk time, conversion %, avg duration. Easy to adjust.
- **Recent calls:** top 25, no pagination. Swap to `->paginate(15)` later if needed.
- **Mobile API untouched** — KPI targets are backoffice-only for now.

## 8. Task list

### Phase 1 — KPI targets persistence (vertical slice: DB → model → form → save)
1. Create migration `create_agent_kpi_targets_table` (FK + unique on `user_id`); run it.
2. Create `App\Models\AgentKpiTarget` + factory; add `User::kpiTarget()` HasOne.
3. Extend `UserRequest` with conditional KPI validation.
4. Extend `StoreUserData` with the two `?int` fields; `fromRequest` nulls them when role ≠ agent.
5. Build `SyncAgentKpiTargetAction` (upsert or delete based on role).
6. Call it from `StoreUserAction` and `UpdateUserAction`.
7. Eager-load `kpiTarget` in `UserController@edit`; flatten values onto the payload.
8. Add conditional "KPI targets" section to `user-form.tsx`; wire `create.tsx` / `edit.tsx`.
9. Add `UserFactory::withKpiTargets()` for tests.
10. Extend Pest tests for store + update (persist, validation ranges, role-switch deletes row).
11. Run `php artisan test --compact --filter=Users` + `vendor/bin/pint --dirty --format agent`.

### Phase 2 — Agent detail page (vertical slice: query → controller → page)
12. Update `CallLogFactory` outcome list to include `'Interested'` and `'Call Back Requested'`.
13. Create `App\Data\Agents\AgentDetail` DTO + `ShowAgentDetailAction` with the three queries; centralize `CONVERSION_OUTCOMES`.
14. Create `Backoffice\AgentController@show(User $agent)`; 404 unless `$agent->hasRole('agent')`.
15. Add route `GET backoffice/agents/{agent}` to `routes/web.php`.
16. Build `resources/js/components/kpi-progress-bar.tsx`.
17. Build `resources/js/pages/backoffice/agents/show.tsx` (progress bars, 30-day cards, recent-calls table).
18. Write `tests/Feature/Backoffice/Agents/ShowTest.php` covering §6 bullets.
19. Run filtered tests + Pint.

### Phase 3 — Wire the dashboard link
20. Add `users.id` to `AnalyticsService::getTopAgentsThisWeek` selection + PHPDoc shape.
21. Update `TopAgentRow` type in `top-agents-list.tsx` and `dashboard.tsx`; wrap agent name in `<Link>` to `/backoffice/agents/${row.id}`.
22. Extend `tests/Feature/DashboardTest.php` to assert `id` is present.
23. Final pass: `php artisan test --compact` + `vendor/bin/pint --dirty --format agent` + `npm run build`.
