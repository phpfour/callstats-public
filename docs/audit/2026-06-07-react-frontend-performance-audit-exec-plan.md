# Exec Plan — Fixing the React/Inertia Frontend Audit

- **Date:** 2026-06-07
- **Source audit:** `docs/audit/2026-06-07-react-frontend-performance-audit.md`
- **Constraints:** No new npm dependencies without approval (CLAUDE.md). No JS test runner in the repo — verification is `npm run build` + `types:check` + `lint:check` + the existing Pest feature tests. Every change must preserve the rendered DOM and the page's prop contract so `LeaderboardTest` and the list-page tests stay green.

---

## 1. Intent
Remove the wasted render work and bundle weight flagged in the audit — concentrated in the leaderboard page, plus `columns` identity across three list pages — without changing any visible behavior or the server-side prop contract.

## 2. Component & data-flow changes
- **Leaderboard** moves from *effect-synced state* to *derived-during-render*: `sortedRows` and `totalConversions` become `useMemo` values computed from `rows`; the empty-first-paint state is gone. `Medal` and the rank label become pure, hoisted helpers. The live-stats `fetch` gains an `AbortController` and cancels on unmount.
- **List pages** (`leads`, `users`, `agents/show`) keep their `columns` definitions but wrap them in `useMemo` so `DataTable`'s existing `useMemo([columns])` stops busting every render. No change to `DataTable` itself.
- **Charts** (optional) load lazily via `React.lazy` + `Suspense`, so the dashboard's counter cards and panels paint before `recharts` evaluates.
- No data-flow change crosses the client/server boundary — all props and routes stay identical.

## 3. Existing files to modify
- `resources/js/pages/backoffice/leaderboard.tsx` — FE-1, FE-2, FE-3, FE-4, FE-5, FE-6, FE-7.
- `resources/js/components/charts/outcome-breakdown.tsx` — FE-6 (recharts `Cell` key).
- `resources/js/pages/backoffice/leads/index.tsx` — FE-8 (`useMemo` columns).
- `resources/js/pages/backoffice/users/index.tsx` — FE-8.
- `resources/js/pages/backoffice/agents/show.tsx` — FE-8.
- `resources/js/pages/backoffice/dashboard.tsx` — FE-9 (lazy chart boundaries), if Phase 4 is taken.

## 4. New files to create
- None required for FE-1–FE-8.
- FE-9 (optional) may add `resources/js/components/charts/lazy.ts` (or inline `React.lazy` wrappers) — only if we keep the chart components in their own module and want a clean lazy entry. Decide during Phase 4.

## 5. Implementation strategy (per finding)
- **FE-1** — Replace `import * as Icons from 'lucide-react'` with `import { Trophy, Medal as MedalIcon, Award } from 'lucide-react'` (alias to avoid clashing with the hoisted `Medal` wrapper) and update the three usages.
- **FE-2** — Delete the two `useState` + the syncing `useEffect`; compute `const sortedRows = useMemo(() => [...rows].sort(byConversions), [rows])` and `const totalConversions = useMemo(() => rows.reduce(...), [rows])`.
- **FE-3** — Delete `computeRankLabel`; render the rank inline as `#${index + 1} of ${sortedRows.length}` (the list is already sorted, so position === index).
- **FE-4** — Hoist `Medal` to module scope as a pure function component.
- **FE-5** — Wrap the `fetch` in an `AbortController`; pass `{ signal }`; ignore `AbortError`; `return () => controller.abort()` from the effect. (Keeps behavior identical and StrictMode-safe with no new dependency.) The missing `/backoffice/leaderboard/stats` endpoint is a **separate product decision** — see Risks; this pass only makes the client code safe.
- **FE-6** — Change `key={index}` → `key={row.agent_id}` in the standings map, and the recharts `Cell` key to a stable slice key (`key={slice.outcome}`).
- **FE-7** — Replace the per-row inline `style={{ display:'flex', ... }}` with the Tailwind classes already used elsewhere in the file (`flex items-center justify-between px-1 py-3`); convert the static card `borderLeft` to a hoisted constant or a Tailwind border utility.
- **FE-8** — Wrap each in-component `columns` array in `useMemo` with the correct dependency list (e.g. `leads` closes over `setLeadToDelete` → `useMemo([])` is safe since the setter is stable; verify each closure). No `DataTable` change.
- **FE-9** (optional) — `const WeeklyCallVolumeChart = React.lazy(() => import('@/components/charts/weekly-call-volume').then(m => ({ default: m.WeeklyCallVolumeChart })))`, etc., each wrapped in `<Suspense fallback={<Skeleton/>}>`.

## 6. Test & verification plan
- **Guardrails after every phase:** `npm run types:check`, `npm run lint:check`, `npm run build` (catches the `lazy`/Suspense and import changes), `npm run format:check`.
- **Behavior guard:** `php artisan test --compact --filter=Leaderboard` and the list-page tests (`Leads/IndexTest`, `Users/IndexTest`, `Agents/ShowTest`) — these assert the Inertia component name, props, and that the page renders, so a broken refactor surfaces. They pass unchanged because the prop contract and rendered output are preserved.
- **Manual smoke (optional):** load `/backoffice/leaderboard` and `/backoffice/dashboard` via the running app; confirm the standings render sorted, medals on the top three, totals correct, and (Phase 4) charts appear after a skeleton.
- **No new test framework** is added — see Risks.

## 7. Risks and assumptions
- **No JS unit tests exist.** Frontend regressions can't be caught by a component test; we rely on TypeScript, ESLint, the production build, and the server-side Pest tests. Adding Vitest + Testing Library would give real coverage but is a **dependency + scope change requiring approval** — proposed as a follow-up, not part of this plan.
- **FE-5 / the `/stats` endpoint:** `/backoffice/leaderboard/stats` is not defined server-side and 404s today; the "Online now" card already degrades to `…`. This plan only makes the client fetch abortable/StrictMode-safe. Whether to (a) build the endpoint, or (b) remove the card, is a product decision left out of the perf pass.
- **FE-1 naming:** the file has both a `Medal` lucide icon and a `Medal` wrapper component — the import must be aliased to avoid a collision.
- **FE-9 is opt-in:** lazy charts add a Suspense fallback flash on the dashboard; acceptable, but it's polish and slightly changes first-render timing, so it's isolated in its own phase and can be skipped.
- **`useMemo` correctness (FE-8):** dependency arrays must include any state/props the cells close over; an empty array is only valid when the closed-over values are stable (React setters, route helpers). Each page is verified individually.
- **No behavioral change intended anywhere** — if any Pest assertion shifts, the refactor is wrong, not the test.

## 8. Task list (phased vertical slices)

**Phase 1 — Leaderboard hot-path cleanup (FE-1, FE-2, FE-3, FE-4, FE-5, FE-6, FE-7)**
*One slice, one file (+ the recharts `Cell` key): the highest-value work.*
1.1 Named lucide imports (aliased); hoist `Medal` to module scope.
1.2 Replace effect-synced state with `useMemo`-derived `sortedRows`/`totalConversions`.
1.3 Delete `computeRankLabel`; inline `#${index+1} of …`.
1.4 Stable `key={row.agent_id}`; Tailwind classes for the per-row/static inline styles.
1.5 `AbortController` + cleanup on the live-stats fetch.
1.6 `outcome-breakdown.tsx`: stable `Cell` key.
1.7 `types:check` + `lint:check` + `build` + `--filter=Leaderboard` green.

**Phase 2 — Column identity across list pages (FE-8)**
2.1 `useMemo` the `columns` in `leads/index.tsx`, `users/index.tsx`, `agents/show.tsx` (verify each dependency list).
2.2 `types:check` + `lint:check` + `build`; run `Leads/IndexTest`, `Users/IndexTest`, `Agents/ShowTest`.

**Phase 3 — Lazy-load dashboard charts (FE-9, optional)**
3.1 `React.lazy` + `Suspense` (skeleton fallback) around the three chart components in `dashboard.tsx`.
3.2 `build` (confirm a separate recharts chunk) + `DashboardTest`; manual smoke for the fallback.

**Follow-ups (not in this plan, need approval)**
- Decide and implement the `/backoffice/leaderboard/stats` endpoint, or remove the "Online now" card.
- Add Vitest + Testing Library for real frontend component coverage.
