# React / Inertia Frontend Performance Audit — Call Stats

- **Date:** 2026-06-07
- **Scope:** React 19 + Inertia v3 SPA frontend (`resources/js`) — pages, shared components, charts, and data tables.
- **Ruleset:** Vercel React Best Practices (bundle size, client data-fetching, re-render, rendering, JS performance).
- **Method:** Static review of `resources/js/pages`, `resources/js/components`, and `resources/js/app.tsx`. No code changed — read-only findings.

This is an **Inertia SPA**, not Next.js, so the RSC / server-action / API-route-waterfall rules don't apply. Inertia already route-splits each page chunk, and the shadcn/Radix `import * as X` patterns are single-module namespaces (not barrels), so they're fine. The codebase is mostly clean — module-level `columns` in `call-logs/index` and `reports`, a hoisted `CounterCard`, and a memoized `DataTable`. The problems concentrate in the recently-added **leaderboard page**, with a few re-render issues elsewhere.

---

## Leaderboard page — `resources/js/pages/backoffice/leaderboard.tsx`

### FE-1. Barrel namespace import of `lucide-react` (`bundle-barrel-imports`) — line 2
```ts
import * as Icons from 'lucide-react';
```
A namespace import of an icon barrel pulls the whole module into the import graph and defeats reliable tree-shaking (Vite usually prunes it, but the rule exists because it's fragile across bundler/SSR configs and slows cold transforms). Only three icons are used.

**Fix:** `import { Trophy, Medal, Award } from 'lucide-react';`

### FE-2. Derived state synced through `useEffect` (`rerender-derived-state-no-effect`) — lines 54–68
```ts
const [sortedRows, setSortedRows] = useState<LeaderboardRow[]>([]);
const [totalConversions, setTotalConversions] = useState(0);
useEffect(() => {
    setSortedRows([...rows].sort((a, b) => b.conversions - a.conversions));
    setTotalConversions(rows.reduce((sum, row) => sum + row.conversions, 0));
}, [rows]);
```
`sortedRows`/`totalConversions` are pure functions of `rows`. Storing them in state + an effect means the **first paint renders an empty table**, then the effect fires and forces a second render — a visible flash and a wasted render cycle every time props change. Derive during render.

**Fix:**
```ts
const sortedRows = useMemo(
    () => [...rows].sort((a, b) => b.conversions - a.conversions),
    [rows],
);
const totalConversions = useMemo(
    () => rows.reduce((sum, row) => sum + row.conversions, 0),
    [rows],
);
```

### FE-3. O(n²) rank computation (`js-min-max-loop` / rendering) — lines 43–49, 79–82
```ts
const rankLabels = sortedRows.map((row) => computeRankLabel(row, sortedRows));
// computeRankLabel re-sorts the ENTIRE array and findIndex() for every row
```
The list is already sorted, yet each row re-sorts the full array and scans it — quadratic work on every render. For an already-sorted list the rank is just the index.

**Fix:** `#${index + 1} of ${sortedRows.length}` inline; delete `computeRankLabel`.

### FE-4. Component defined inside the component (`rerender-no-inline-components`) — lines 85–97
```ts
function Medal({ position }: { position: number }) { ... }
```
`Medal` is re-created on every render of `Leaderboard`, so React sees a new component type and **remounts every medal node** each render instead of updating. Hoist it to module scope.

### FE-5. Raw `fetch` in effect, no cleanup/dedup (`client-swr-dedup`) — lines 71–77
```ts
useEffect(() => {
    fetch('/backoffice/leaderboard/stats').then(...).catch(...);
}, []);
```
No `AbortController`/cleanup, so it can `setState` after unmount; with `strictMode: true` (set in `app.tsx`) it double-fires in dev. There's also no request dedup/caching. *(Separately: that route isn't defined server-side — it 404s — a latent correctness bug worth fixing or removing.)*

**Fix:** SWR (`useSWR('/backoffice/...', fetcher)`) or an `AbortController` with cleanup in the effect.

### FE-6. Index keys on a reorderable list (rendering correctness) — line 151 (and `key={index}` on recharts `Cell` in `outcome-breakdown.tsx`)
```ts
{sortedRows.map((row, index) => (<div key={index} ...>))}
```
The list is sorted/reordered, so positional keys cause React to mis-match DOM nodes across reorders. Use the stable `row.agent_id`.

### FE-7. Per-row inline style objects (`rendering-hoist-jsx` / `js-batch-dom-css`) — lines 152–159
Each row allocates a fresh `style={{ display:'flex', ... }}` object, and the static card uses `style={{ borderLeft: ... }}`. The rest of the file uses Tailwind; these should too (or hoist the static object).

---

## List pages — unstable `columns` identity (`rerender-memo`)

### FE-8. `columns` defined inside the component — `leads/index.tsx:141`, `users/index.tsx:90`, `agents/show.tsx:77`
These define the `columns` array **inside** the component, so a new array is created every render. `DataTable` memoizes on `[columns]` (`data-table.tsx:42`), so that memo busts every render and tanstack-table rebuilds its column model unnecessarily. Note `call-logs/index.tsx` and `reports/agent-performance.tsx` already do this correctly (module-level `const columns`).

**Fix:** wrap in `useMemo` (needed where cells close over state like `setLeadToDelete`), or hoist to module scope where there's no closure.

---

## Charts / bundle

### FE-9. Heavy `recharts` loaded eagerly (`bundle-dynamic-imports`) — `components/charts/*`
`recharts` is large and imported statically into the dashboard. Inertia route-splits the dashboard page so it doesn't hit other routes (mitigated), but it still bloats the dashboard chunk and blocks that page's first paint. Consider `React.lazy` + `<Suspense>` around the chart components so counters/panels paint first.

---

## Summary

| ID | Issue | Severity | Recommendation |
|----|-------|----------|----------------|
| FE-1 | `import * as Icons from 'lucide-react'` — barrel namespace import | High | Named imports: `{ Trophy, Medal, Award }` |
| FE-2 | `sortedRows`/`totalConversions` derived via `useState`+`useEffect` → empty first paint + double render | High | Derive during render with `useMemo` |
| FE-3 | `computeRankLabel` re-sorts the whole list per row → O(n²) | Medium | List is pre-sorted; use `index + 1` |
| FE-4 | `Medal` component defined inside the component → remounts each render | Medium | Hoist to module scope |
| FE-5 | Raw `fetch` in effect: no abort/cleanup, double-fires under StrictMode, no dedup | Medium | SWR or `AbortController` + cleanup (and fix the 404 route) |
| FE-6 | `key={index}` on a sorted/reorderable list (and recharts `Cell`) | Low-Med | Use stable `row.agent_id` |
| FE-7 | Per-row inline `style` objects allocated each render | Low | Tailwind classes / hoist static styles |
| FE-8 | `columns` defined inside component in leads/users/agents pages → busts `DataTable` memo | Medium | `useMemo` the columns (or hoist) |
| FE-9 | `recharts` imported eagerly into the dashboard chunk | Low-Med | `React.lazy` + `Suspense` for chart components |

**Where to start:** FE-1 through FE-5 are all in the single leaderboard file and account for the bulk of the wasted work (double renders, O(n²) ranking, remounts, uncleaned fetch) — a focused cleanup there is the highest value. FE-8 is a cheap, mechanical win across three list pages. FE-6/FE-7/FE-9 are polish.
