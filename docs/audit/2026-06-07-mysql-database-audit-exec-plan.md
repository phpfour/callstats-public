# Exec Plan — Fixing the MySQL Audit Findings

- **Date:** 2026-06-07
- **Source audit:** `docs/audit/2026-06-07-mysql-database-audit.md`
- **Apply method:** Additive migrations + a **targeted reseed of `agent_scorecards` only**. No `migrate:fresh` and no full-database wipe (see §7).

---

## 1. Intent
Re-shape the `agent_scorecards` snapshot into a properly typed, FK-backed, normalized model and rewrite the leaderboard and hot `call_logs`/`leads` query paths to be correct and index-sargable.

## 2. Schema design

### `agent_scorecards` (redesigned)
| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` PK | |
| `user_id` | `BIGINT UNSIGNED` | FK → `users.id`, `cascadeOnDelete` (replaces `agent_name`/`agent_email`) |
| `scorecard_date` | `DATE NOT NULL` | replaces `VARCHAR(255)` |
| `status` | `VARCHAR(20) NOT NULL DEFAULT 'final'` | shrunk from 255 |
| `total_calls` | `INT UNSIGNED NOT NULL DEFAULT 0` | |
| `connected_calls` | `INT UNSIGNED NOT NULL DEFAULT 0` | |
| `conversions` | `INT UNSIGNED NOT NULL DEFAULT 0` | |
| `talk_time_seconds` | `INT UNSIGNED NOT NULL DEFAULT 0` | was `FLOAT` |
| `conversion_rate` | `DECIMAL(5,2) NOT NULL DEFAULT 0` | was `FLOAT`; kept (snapshot table) |
| `review` | `BOOLEAN NOT NULL DEFAULT false` | promoted out of `raw_payload` JSON-in-text |
| `raw_payload` | `JSON NULL` | retained for audit only, never queried with `LIKE` |
| `created_at`/`updated_at` | timestamps | |

**Indexes:**
- `UNIQUE (user_id, scorecard_date)` — one snapshot per agent per day; also serves the per-agent range lookup (leftmost `user_id`).
- `INDEX (scorecard_date, status)` — date-window + status scoped scans (leaderboard range, reports).
- Drop the standalone `scorecard_date` index (redundant — issue 14).

### `agent_scorecard_outcomes` (new child table — fixes 1NF issue 10)
| Column | Type | Notes |
|---|---|---|
| `id` | `BIGINT UNSIGNED` PK | |
| `agent_scorecard_id` | `BIGINT UNSIGNED` | FK → `agent_scorecards.id`, `cascadeOnDelete` |
| `outcome` | `VARCHAR(50) NOT NULL` | one row per top outcome |
| `count` | `INT UNSIGNED NOT NULL DEFAULT 0` | |

**Indexes:** `UNIQUE (agent_scorecard_id, outcome)`; `INDEX (outcome)` for "interested days" lookups.

### `call_logs`
- Add `INDEX (user_id, called_at)` (issue 7). No column changes.

### `leads` (Phase 3, lower priority)
- Add `FULLTEXT (name)` for search; phone search switched to prefix match.

**FK strategy:** `agent_scorecards.user_id` and `agent_scorecard_outcomes.agent_scorecard_id` are real FKs with cascade delete. Scorecard data is 100% derived from `call_logs` (see `AgentScorecardSeeder`), so the table is **rebuilt from source via a scoped reseed**, not back-filled in place (see §7).

## 3. Apply method (no `migrate:fresh`)
The original `create_agent_scorecards_table` migration has already run, so editing it would not re-execute. Instead:

1. A **new** migration **drops and recreates only `agent_scorecards`** with the corrected schema. Dropping this one derived table loses nothing recoverable — every value is regenerable from `call_logs`. No other table is touched; the rest of the database is preserved.
2. A **new** migration creates `agent_scorecard_outcomes`.
3. A **new** migration adds the `call_logs(user_id, called_at)` index.
4. Apply with `php artisan migrate` (forward-only, no fresh).
5. Repopulate with `php artisan db:seed --class=AgentScorecardSeeder` — the seeder already deletes only scorecard rows and re-derives them from `call_logs`, so it rebuilds both `agent_scorecards` and the new `agent_scorecard_outcomes` without disturbing `users`, `leads`, `call_logs`, etc.

> Production-safe alternative (zero-downtime) is documented in §7 — additive nullable columns + back-fill + online DDL swap — for the case where the table is live and cannot be dropped.

## 4. Existing files to modify
- `app/Models/AgentScorecard.php` — fillable, casts (`scorecard_date:date`, `review:bool`, `conversion_rate:decimal:2`), `belongsTo(User)`, `hasMany(AgentScorecardOutcome)`.
- `app/Models/User.php` — add `scorecards(): HasMany`.
- `app/Services/LeaderboardService.php` — rewrite `build()` (aggregate queries) and `featuredAgent()` (random seek).
- `database/seeders/AgentScorecardSeeder.php` — write `user_id`, `DATE`, `review` bool, `DECIMAL` rate, insert child outcome rows (drop `top_outcomes` string); keep idempotent delete-then-rebuild.
- `database/factories/AgentScorecardFactory.php` — `user_id` via `forAgent()`, `review` bool, outcomes via `afterCreating`.
- `app/Actions/Agents/ShowAgentDetailAction.php` — `todayStats()` half-open range (issue 8).
- `app/Actions/CallLogs/ListCallLogsAction.php` — `calledFrom`/`calledTo` half-open ranges.
- `app/Actions/Leads/ListLeadsAction.php` — `assignedFrom`/`assignedTo` ranges + search (Phase 3).

## 5. New files to create
- `database/migrations/2026_06_07_000001_redefine_agent_scorecards_table.php` — drop + recreate `agent_scorecards` (corrected schema).
- `database/migrations/2026_06_07_000002_create_agent_scorecard_outcomes_table.php`
- `database/migrations/2026_06_07_000003_add_user_called_at_index_to_call_logs_table.php`
- `database/migrations/2026_06_07_000004_add_fulltext_index_to_leads_table.php` (Phase 3)
- `app/Models/AgentScorecardOutcome.php`
- `tests/Feature/Backoffice/LeaderboardTest.php`
- `tests/Unit/Services/LeaderboardServiceTest.php`
- `tests/Feature/CallLogs/CallLogDateFilterTest.php` (sargable range correctness)

## 6. Query strategy
**Leaderboard `build($from, $to)` — from `1 + 3N` queries to 3 bounded queries:**
1. Agents lookup (`User::role(AGENT)`), keyed by id.
2. **One** main aggregate: `GROUP BY user_id` over `scorecard_date BETWEEN from AND to` returning `COUNT(*) days`, `SUM(total_calls|connected_calls|conversions|talk_time_seconds)`, and `SUM(CASE WHEN status='flagged' OR review THEN 1 ELSE 0 END) AS flagged_days` — fixes the precedence bug (1), the `created_at`→`scorecard_date` filter (2), and the N+1 (3) in one move.
3. **One** outcomes aggregate: join `agent_scorecard_outcomes` where `outcome='Interested'`, `GROUP BY user_id`, `COUNT(DISTINCT scorecard_id) AS interested_days`.
4. Merge (2)+(3) by `user_id` in PHP, compute `conversion_rate` on read, `usort` by conversions. SUMs happen in SQL, not PHP.

**`featuredAgent()`:** replace `ORDER BY RAND()` with a random-id seek — `random_int(MIN(id), MAX(id))` then `where('id','>=',$n)->first()` with wrap-around fallback (eager-load `user`).

**Sargable ranges:** every `whereDate(col, ...)` becomes a half-open range — "today" → `>= today 00:00 AND < tomorrow 00:00`; "to" filters use `< to+1day` to stay inclusive. Lets `call_logs(user_id, called_at)` actually be used.

## 7. Test plan
- **LeaderboardServiceTest (unit):** ranking order by conversions; date window excludes out-of-range `scorecard_date`; `flagged_days` counts only the agent's own flagged/review rows (regression for issue 1 — seed another agent's `review=true` row and assert it is **not** counted); `interested_days` from child table; conversion_rate math; empty-data path.
- **LeaderboardTest (feature):** `GET /backoffice/leaderboard` 200, payload shape, `featured` non-null with seeded data and null when empty; assert query count is bounded (via `DB::listen`) to lock in the N+1 fix.
- **CallLogDateFilterTest (feature):** boundary rows at `00:00:00` start and `23:59:59` end of range are included/excluded correctly under the half-open range; today-stats counts a call at `23:59`.
- **Regression:** run existing CallLog/Lead/Report/AgentDetail tests unchanged — outputs must be identical after the range refactor.
- Each phase: `php artisan test --compact --filter=...` green before moving on; `vendor/bin/pint --dirty --format agent` after PHP edits.

## 8. Risks and assumptions
- **No `migrate:fresh`:** the apply method (§3) drops and recreates **only** `agent_scorecards` and adds tables/indexes additively. Every other table is preserved. Assumes `call_logs` rows already exist (seeded by `LastTenDaysCallLogSeeder`) so the scoped reseed can re-derive scorecards.
- **Single-table drop is safe because the table is derived:** no other table FKs into `agent_scorecards`, and all its data is recomputable from `call_logs`. If a row carried non-derivable data this method would be unsafe — it does not.
- **Production / live-table path (alternative to dropping):** if `agent_scorecards` is live and cannot be dropped, replace the drop+recreate with additive nullable columns (`user_id`, `scorecard_date` DATE, `review`), back-fill (`user_id` via `agent_email`→`users` join, parse the date string, read `review` from `raw_payload`), populate `agent_scorecard_outcomes` from the `top_outcomes` string, then drop the old columns/index with online DDL (`ALGORITHM=INPLACE, LOCK=NONE`). Slower but zero-downtime.
- **`name`→`user_id` ambiguity:** duplicate `agent_name` values would be ambiguous under the live-table back-fill; the scoped-reseed method sidesteps this since the seeder already has `user_id`.
- **`conversion_rate` kept, not dropped:** chose to keep it as a precomputed `DECIMAL` (snapshot table's purpose); trade-off is it must stay consistent with its inputs (only ever written by the seeder/aggregator).
- **Child table vs JSON for outcomes:** chose the normalized child table over JSON+generated column — indexable and 1NF-correct, at the cost of a heavier seeder write path. JSON is the lighter alternative if write volume matters.
- **`status` left as `VARCHAR(20)`** rather than a lookup/enum cast — proportionate for 3 states; revisit if states proliferate.
- **FULLTEXT (Phase 3):** InnoDB FULLTEXT has a default 3-char minimum token (`innodb_ft_min_token_size`); short names won't match. Phone prefix search assumes a normalized stored format. Lower-value; can be deferred.
- **Collation (issue 16)** and **`outcome` lookup/check (issue 15)** are out of core scope (low severity, table-rebuild cost) — optional Phase 4.

## 9. Task list (phased vertical slices)

**Phase 1 — Scorecard redesign, end-to-end (issues 1, 2, 3, 4, 5, 6, 9, 10, 11, 12, 14, status of 15)**
*One slice: schema + models + seeder + service + tests must land together to keep the tree green.*
1.1 Add `redefine_agent_scorecards_table` migration (drop + recreate, corrected schema + indexes).
1.2 Add `create_agent_scorecard_outcomes_table` migration.
1.3 Add `AgentScorecardOutcome` model; update `AgentScorecard` (casts, FK, `hasMany`); add `User::scorecards()`.
1.4 Update `AgentScorecardSeeder` + `AgentScorecardFactory` to populate `user_id`, `DATE`, `review`, `DECIMAL`, and child outcome rows.
1.5 Rewrite `LeaderboardService::build()` (3 bounded aggregate queries + merge) and `featuredAgent()` (random seek).
1.6 Write `LeaderboardServiceTest` + `LeaderboardTest` (incl. flagged-cross-contamination regression and bounded-query-count assertion).
1.7 Apply: `php artisan migrate` then `php artisan db:seed --class=AgentScorecardSeeder`; run filtered tests; `pint --dirty`.

**Phase 2 — `call_logs` index + sargable date ranges (issues 7, 8)**
2.1 Add `add_user_called_at_index_to_call_logs_table` migration; `php artisan migrate`.
2.2 Refactor `ShowAgentDetailAction::todayStats()` and `ListCallLogsAction` date filters to half-open ranges.
2.3 Add `CallLogDateFilterTest`; run existing CallLog/Report/AgentDetail tests for parity; `pint`.

**Phase 3 — Leads search & date hardening (issues 13, 8)**
3.1 Add `add_fulltext_index_to_leads_table` migration; `php artisan migrate`.
3.2 Refactor `ListLeadsAction` — `assignedFrom`/`assignedTo` ranges; name FULLTEXT / phone prefix search.
3.3 Update/extend lead-listing tests; `pint`.

**Phase 4 — Optional hardening (issues 15 outcome, 16)**
4.1 `outcome` lookup table or DB check constraint.
4.2 Collation alignment to `utf8mb4_0900_ai_ci`.
4.3 Tests / verification; `pint`.
