# MySQL Database Audit — Call Stats

- **Date:** 2026-06-07
- **Scope:** Schema (migrations), Eloquent models, and query paths in the leaderboard, reports, and listing actions.
- **Method:** Static review of `database/migrations`, `app/Models`, `app/Services`, and `app/Actions`. No code changed and no migrations run — read-only findings.

The newest feature — `agent_scorecards` + `LeaderboardService` — is where most of the risk concentrates.

---

## 1. `agent_scorecards` schema (the leaderboard snapshot table)

This table was added to *avoid* aggregating `call_logs` on every page load, but its column design reintroduces the cost it was meant to remove.

### 1a. Dates stored as `VARCHAR(255)` (`scorecard_date`)
`scorecard_date` holds values like `"2026-06-06"` in a 255-char string column. Consequences:
- Range filters become lexicographic string comparisons, not date comparisons.
- The optimizer can't reason about it as a date; no range-scan math, broken sorting across formats.
- ~4× the storage of a 3-byte `DATE`, which bloats the index too.

**Fix:** `DATE NOT NULL`.

### 1b. Agent identity denormalized as strings, no FK (`agent_name`, `agent_email`)
Each row copies the agent's name/email instead of carrying `user_id`. The leaderboard then *joins reality back together* with `where('agent_name', $agent->name)`.
- No referential integrity — a renamed agent silently orphans all prior scorecards; two agents sharing a name merge.
- The lookup key is a 255-char string with **no index** (see 1d), so every lookup is a full table scan.

**Fix:** `user_id BIGINT UNSIGNED` + FK to `users`; drop the copied strings (or keep email only as a denormalized convenience, never as the join key).

### 1c. `FLOAT` for `talk_time_seconds` and `conversion_rate`; `conversion_rate` is redundant
`FLOAT` is approximate — totals drift and rates won't compare equal. `talk_time_seconds` is whole seconds and should be an integer; `conversion_rate` is a derived value (`conversions/total_calls`) stored *and* recomputed in PHP, so it can disagree with its own inputs.

**Fix:** `talk_time_seconds` → `INT UNSIGNED`; `conversion_rate` → `DECIMAL(5,2)` or drop it and compute on read.

### 1d. No index on the actual lookup column
Indexes exist on `scorecard_date` and `(scorecard_date, status)` — but the service never filters by `scorecard_date`; it filters by `agent_name` and `created_at`, neither of which is indexed. The indexes that exist are unused and the queries that run are unindexed.

### 1e. Redundant index
`index('scorecard_date')` is fully covered by the leftmost prefix of `index(['scorecard_date','status'])`. The standalone index is dead weight on every write.

**Fix:** drop the single-column index.

### 1f. Multi-value data in a single column (`top_outcomes`)
Stored as `"Successful Contact,Interested,Follow-up"` and queried with `LIKE '%Interested%'`. This violates 1NF, can never use an index, and matches substrings ("Not Interested" matches "Interested").

**Fix:** a child table `scorecard_outcomes` (or a junction), or at minimum a `JSON` column with generated-column indexing.

### 1g. JSON kept in `TEXT` and queried with `LIKE` (`raw_payload`)
`->where('raw_payload', 'like', '%"review":true%')` is a full-scan substring match against serialized JSON. Fragile (whitespace/key-order sensitive) and unindexable.

**Fix:** native `JSON` column with a generated `BOOLEAN` column + index, or promote `review` to a real column.

### 1h. `status` as `VARCHAR(255)`
A handful of states (`draft`/`final`/`flagged`) in a 255-char column. Minor, but oversized for an indexed column.

**Fix:** short `VARCHAR(20)` (or lookup table).

---

## 2. `LeaderboardService::build()` — query patterns

### 2a. N+1 with PHP-side aggregation
The method loads all agents, then per agent runs **three** queries (scorecards, "interested days", "flagged days") and sums `total_calls`/`conversions`/`talk_time` in a PHP `foreach`. For *N* agents that's `1 + 3N` queries plus rows hauled into PHP only to be summed — exactly the aggregation the snapshot table was meant to eliminate.

**Fix:** a single `GROUP BY user_id` query with `SUM(...)`/conditional aggregates over the date range.

### 2b. `whereDate('created_at', …)` — wrong column *and* unindexable
- Wrapping `created_at` in `DATE()` prevents any index use (function on the indexed column).
- Semantically wrong: it filters on *row insertion time*, not the business day (`scorecard_date`). A scorecard for 2026-06-01 backfilled today lands in the wrong window.

**Fix:** filter `scorecard_date BETWEEN ? AND ?` as a bare range on a real `DATE` column.

### 2c. `orWhere` precedence bug in `flaggedDays` (correctness)
```php
->where('agent_name', $agent->name)
->where('status', 'flagged')
->orWhere('raw_payload', 'like', '%"review":true%')
```
SQL boolean precedence makes this `(agent_name = X AND status = 'flagged') OR raw_payload LIKE '%...%'`. The `orWhere` is **not scoped to the agent**, so it counts every agent's `review:true` rows into this agent's `flagged_days`. Result: inflated, cross-contaminated counts — and a full scan. A real bug, not just a perf issue.

**Fix:** wrap the OR in a closure (`->where(fn ($q) => $q->where('status','flagged')->orWhere(...))`) — and once `review` is a real column, drop the `LIKE` entirely.

### 2d. `featuredAgent()` uses `ORDER BY RAND()`
`orderByRaw('RAND()')->first()` assigns a random value to every row, sorts the whole table, and discards all but one. O(n log n) full scan + filesort that grows with the table.

**Fix:** `offset` a random row by count, or pick a random `id` between min/max and seek.

---

## 3. `call_logs` — the real source-of-truth table

### 3a. No composite `(user_id, called_at)` index
The hottest queries (`ShowAgentDetailAction` today/30-day stats, `AgentPerformanceReportAction`, recent-calls `latest('called_at')`) all filter by `user_id` and range/sort by `called_at`. The FK gives a single-column index on `user_id` only, so MySQL filters by user then sorts/filescans by date.

**Fix:** add `index(['user_id', 'called_at'])` (equality column first, range/sort second — leftmost-prefix rule).

### 3b. `whereDate('called_at', …)` defeats the index
`todayStats()` and the list filters use `whereDate('called_at', today)` / `whereDate('called_at', '>=', $from)`, wrapping the column in `DATE()`.

**Fix:** half-open range — `where('called_at', '>=', $start)->where('called_at', '<', $end)` — which is sargable and uses 3a.

### 3c. `outcome` is free-text `VARCHAR`
Conversion logic depends on exact strings (`'Missed'`, `'No Answer'`, `IN (...)`). A typo or casing drift silently miscounts.

**Fix:** a lookup table or DB-level enum/check constraint.

---

## 4. `leads` — listing

### 4a. Leading-wildcard search
`where('name','like','%term%')->orWhere('phone_number','like','%term%')` — the leading `%` makes both unindexable; a full scan that worsens linearly with lead volume.

**Fix:** FULLTEXT index for `name`, and for phone search prefer a normalized/prefix match (`'term%'`) or a dedicated normalized phone column.

### 4b. `whereDate('assigned_at', …)`
Same sargability problem as 3b; use a range.

---

## 5. Connection config

### 5a. Collation `utf8mb4_unicode_ci`
On MySQL 8 the modern default is `utf8mb4_0900_ai_ci` (better Unicode handling and faster). Charset `utf8mb4` is correct. Low priority; align if standardizing on MySQL 8.

---

## Summary

| ID | Issue | Severity | Recommendation |
|----|-------|----------|----------------|
| 1 | `flaggedDays` `orWhere` not scoped to agent → counts other agents' rows (correctness bug) | Critical | Wrap OR in a closure; drop the `LIKE` once `review` is a real column |
| 2 | Leaderboard filters/counts on `created_at` (insertion time), not `scorecard_date` (business day) | High | Filter on `scorecard_date`; fixes both correctness and indexability |
| 3 | N+1: `1 + 3N` queries, totals summed in PHP | High | Single `GROUP BY user_id` query with `SUM()`/conditional aggregates |
| 4 | `scorecard_date` stored as `VARCHAR(255)` | High | Change to `DATE NOT NULL` |
| 5 | Agent identity denormalized as `agent_name`/`agent_email`, no FK, used as join key | High | Add `user_id` + FK to `users`; stop joining on name |
| 6 | No index on the leaderboard's actual lookup column (`agent_name`/`user_id`) | High | Index the real lookup key (becomes `user_id` after #5) |
| 7 | No composite index on `call_logs(user_id, called_at)` for all hot queries | High | Add `index(['user_id','called_at'])` |
| 8 | `whereDate()` on `called_at`/`assigned_at`/`created_at` wraps column, defeats index | Medium | Use half-open ranges (`>= start AND < end`) |
| 9 | `featuredAgent()` uses `ORDER BY RAND()` (full scan + filesort) | Medium | Random offset by count, or random-id seek |
| 10 | `top_outcomes` is comma-joined text queried with `LIKE` (violates 1NF, substring false matches) | Medium | Child/junction table, or JSON + generated indexed column |
| 11 | `raw_payload` is JSON-in-`TEXT` queried with `LIKE` | Medium | Native `JSON` column + generated boolean column & index, or promote `review` |
| 12 | `FLOAT` for `talk_time_seconds`/`conversion_rate`; `conversion_rate` redundantly stored | Medium | `INT UNSIGNED` for seconds; `DECIMAL(5,2)` or compute-on-read for rate |
| 13 | Leads search uses leading-wildcard `LIKE '%term%'` | Medium | FULLTEXT for name; prefix/normalized match for phone |
| 14 | Redundant index: `scorecard_date` duplicates prefix of `(scorecard_date, status)` | Low | Drop the standalone `scorecard_date` index |
| 15 | `status` `VARCHAR(255)`; `outcome` free-text | Low | Shrink `status`; lookup table / check constraint for `outcome` |
| 16 | Collation `utf8mb4_unicode_ci` | Low | Move to `utf8mb4_0900_ai_ci` on MySQL 8 |

**Where to start:** #1–#3 are behavioral/correctness and cheap to fix in `LeaderboardService`. #4–#7 are the schema changes that make the leaderboard and `call_logs` queries actually use indexes — the highest perf-per-effort wins. The rest are hardening.
