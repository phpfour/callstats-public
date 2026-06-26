# Exec Plan — Fixing the Laravel Security Audit Findings

- **Date:** 2026-06-07
- **Source audit:** `docs/audit/2026-06-07-laravel-security-audit.md`
- **Apply method:** Code-only changes (validation rules, middleware, route middleware, a new policy, one config file) plus `.env`/deploy documentation. **No migrations, no schema changes, no data backfill** — nothing in this plan touches the database.

---

## 1. Intent
Close the two remotely-reachable authenticated-abuse holes in the mobile API (login brute-force, write-path IDOR), neutralize the spreadsheet formula-injection and unbounded-upload surfaces, and add the missing transport/deployment hardening — without altering any existing behavior for legitimate requests.

## 2. Approach by finding

### Finding 1 (High) — Rate-limit `/api/login`
The Fortify `login` limiter already exists (`FortifyServiceProvider`, 5/min by email+IP) but only guards the web login. Apply the same limiter to the API route.
- Wrap the API login route in `->middleware('throttle:login')`.
- Reuse the existing named limiter rather than defining a new one, so web and API share one policy.

### Finding 2 + 5 (High / Medium) — Ownership enforcement on API writes
The fix is to make the API form requests prove the referenced rows belong to the caller. Two complementary layers:
- **Validation layer:** scope the `exists` rules to the authenticated agent.
  - `StoreCallLogRequest`: `lead_id` → `Rule::exists('leads','id')->where('assigned_to_id', $this->user()->id)`.
  - `StoreReminderRequest`: `lead_id` same as above; `call_log_id` → `Rule::exists('call_logs','id')->where('user_id', $this->user()->id)`.
- **Policy layer (defense-in-depth):** add a `LeadPolicy` with a `logCall`/`addReminder` (or reuse `view`/`update`) ability checking `lead.assigned_to_id === user.id`, and call `$this->authorize(...)` in the controllers. This keeps authorization meaningful even if a future caller bypasses the form request.

Both layers return the standard 403/422 for cross-tenant attempts; legitimate agents (acting on their own assigned leads) are unaffected.

### Finding 6 (Low) — Remove unscoped `user_id` read branch
In `CallLogController::index`, delete the `elseif ($userId = $request->query('user_id'))` branch. Agents are always scoped to their own `user_id`; the param has no legitimate API consumer. (If a backoffice consumer is ever needed, it belongs on a role-gated route, not the agent token surface.)

### Finding 3 (Medium) — Spreadsheet formula injection
Add a single sanitizer applied to every user-derived cell value in both export actions.
- Introduce a small helper (e.g. `App\Support\Spreadsheet\CsvSafe::escape(?string): ?string`) that prefixes a leading `= + - @ \t \r` with `'`.
- Apply it to `notes` and `name` (and any other free-text columns) in `ExportCallLogsAction` and `ExportAgentPerformanceAction`.
- Numeric/derived columns (IDs, counts, durations) are left untouched.

### Finding 4 (Medium) — Bound the import upload
In `LeadImportController::store`, add `'max:5120'` to the `file` rule. No streaming rewrite in this pass — the size cap is the proportionate fix; chunked reading is noted as optional follow-up.

### Finding 7 (Low) — Security headers
Add `app/Http/Middleware/SecurityHeaders.php` setting `Content-Security-Policy`, `Strict-Transport-Security`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`. Append it to the `web` group in `bootstrap/app.php`. Start CSP permissive enough for Inertia/Vite (script/style from self + Vite dev server) to avoid breaking the SPA; tighten later.

### Finding 8 (Low) — CORS
Add `config/cors.php` with `paths => ['api/*']`, explicit `allowed_origins` (env-driven), and `supports_credentials => false` (bearer tokens, no cookies needed).

### Finding 9 (Low/Info) — Deployment hardening
Documentation-only in this repo (cannot set production secrets here): record the required production `.env` values (`APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE`, trusted proxies) in the audit and deployment notes. Confirm `.env` is git-ignored and `APP_KEY` is environment-specific.

## 3. Existing files to modify
- `routes/api.php` — `throttle:login` on the login route; remove the `user_id` read branch usage (controller side).
- `app/Http/Requests/Api/StoreCallLogRequest.php` — caller-scoped `exists` rule for `lead_id`.
- `app/Http/Requests/Api/StoreReminderRequest.php` — caller-scoped `exists` rules for `lead_id` and `call_log_id`.
- `app/Http/Controllers/CallLogController.php` — remove unscoped `user_id` branch; add `authorize()` call (if controller-level policy chosen).
- `app/Http/Controllers/ReminderController.php` — add `authorize()` call (policy layer).
- `app/Http/Controllers/Backoffice/LeadImportController.php` — add `max:5120`.
- `app/Actions/CallLogs/ExportCallLogsAction.php` — apply cell sanitizer.
- `app/Actions/Reports/ExportAgentPerformanceAction.php` — apply cell sanitizer.
- `bootstrap/app.php` — append `SecurityHeaders` to the `web` group.

## 4. New files to create
- `app/Policies/LeadPolicy.php` — ownership checks for call-log/reminder creation.
- `app/Http/Middleware/SecurityHeaders.php` — response security headers.
- `app/Support/Spreadsheet/CsvSafe.php` — formula-injection escaper.
- `config/cors.php` — explicit CORS policy.
- `tests/Feature/Api/ApiLoginThrottleTest.php`
- `tests/Feature/Api/CallLogOwnershipTest.php`
- `tests/Feature/Api/ReminderOwnershipTest.php`
- `tests/Feature/CallLogs/ExportFormulaInjectionTest.php`
- `tests/Feature/Backoffice/LeadImportValidationTest.php`

## 5. Test plan
- **ApiLoginThrottleTest:** the 6th login attempt within a minute returns 429; a successful login still works under the limit. (Use `RateLimiter::clear` between cases or travel time.)
- **CallLogOwnershipTest:** agent A `POST /api/call-logs` for a lead assigned to agent B → 422/403; for their own lead → 201. Mirror for the `callback_at` reminder path.
- **ReminderOwnershipTest:** `POST /api/reminders` with another agent's `lead_id` and another agent's `call_log_id` → rejected; own → 201.
- **ExportFormulaInjectionTest:** seed a call log with `notes = "=HYPERLINK(\"http://evil\")"`; assert the exported cell value is prefixed with `'` (or stored as explicit string).
- **LeadImportValidationTest:** a file over the size cap → validation error; a valid small `.xlsx` → imports.
- **Headers/CORS smoke (optional):** assert `X-Frame-Options`/`X-Content-Type-Options` present on a web response; `config('cors.allowed_origins')` is not `['*']`.
- **Regression:** run existing API CallLog/Reminder/Lead tests — legitimate same-agent flows must be unchanged.
- Each phase: `php artisan test --compact --filter=...` green before moving on; `vendor/bin/pint --dirty --format agent` after PHP edits.

## 6. Risks and assumptions
- **Caller-scoped `exists` vs policy:** doing both is intentional — the scoped rule gives a clean 422 with a field error, the policy is the durable authorization boundary. If only one is wanted, keep the policy.
- **CSP breaking Inertia/Vite:** an over-tight `Content-Security-Policy` can blank the SPA (inline styles, Vite HMR/websocket in dev). Start permissive (`'self'` + Vite origin, allow inline styles), verify the app renders, then tighten. Consider applying HSTS only in production (`APP_ENV=production`) so local HTTP isn't forced to HTTPS.
- **`throttle:login` key:** the limiter keys on `email + ip`; ensure the API request sends `email` (it does) so the key matches the web limiter's shape.
- **Removing the `user_id` branch:** assumes no current mobile client relies on it. It is unreachable for agent tokens today, so removal is behavior-neutral for the shipped client.
- **Import size cap value:** `5120` KB is a placeholder ceiling; adjust to the largest legitimate customer spreadsheet. The cap does not protect against a small-but-deeply-nested zip-bomb — streamed reading (deferred) would.
- **No DB changes:** unlike the MySQL exec plan, nothing here migrates or reseeds. The two plans are independent and can land in either order.

## 7. Task list (phased — highest exploitability first)

**Phase 1 — API auth & authorization (findings 1, 2, 5, 6)**
*The only remotely-reachable authenticated holes; land first.*
1.1 Add `throttle:login` to the `/api/login` route.
1.2 Create `LeadPolicy`; register if not auto-discovered.
1.3 Scope `exists` rules in `StoreCallLogRequest` and `StoreReminderRequest` to the caller.
1.4 Add `authorize()` calls in `CallLogController`/`ReminderController`; remove the unscoped `user_id` read branch.
1.5 Write `ApiLoginThrottleTest`, `CallLogOwnershipTest`, `ReminderOwnershipTest`; run filtered tests + existing API tests; `pint --dirty`.

**Phase 2 — Output & upload hardening (findings 3, 4)**
2.1 Add `CsvSafe` helper; apply in both export actions.
2.2 Add `max:5120` to the lead-import rule.
2.3 Write `ExportFormulaInjectionTest`, `LeadImportValidationTest`; run; `pint`.

**Phase 3 — Transport & CORS (findings 7, 8)**
3.1 Add `SecurityHeaders` middleware; append to the `web` group.
3.2 Add `config/cors.php` with explicit origins.
3.3 Verify the SPA still renders (CSP smoke); optional header/CORS assertions; `pint`.

**Phase 4 — Deployment hardening (finding 9, docs-only)**
4.1 Document required production `.env` values and trusted-proxy config in deployment notes.
4.2 Confirm `.env` is git-ignored and `APP_KEY` is environment-specific.

---

## Appendix — Production deployment checklist (Phase 4)

`.env` is git-ignored (verified) and `.env.example` now carries inline guidance for
the session/CORS keys below. Set the following in the **production** environment
(Laravel Cloud env vars / deploy secrets — not committed):

| Key | Production value | Why |
|---|---|---|
| `APP_ENV` | `production` | Disables dev-only affordances |
| `APP_DEBUG` | `false` | Debug pages leak stack traces, env values, secrets |
| `APP_KEY` | unique per environment | Encryption/signing key; never reuse the dev key |
| `SESSION_SECURE_COOKIE` | `true` | Session cookie only sent over TLS |
| `SESSION_HTTP_ONLY` | `true` | Cookie hidden from JavaScript (XSS theft) |
| `SESSION_SAME_SITE` | `lax` (or `strict`) | CSRF defense-in-depth |
| `CORS_ALLOWED_ORIGINS` | e.g. `https://app.example.com` | Explicit API origin allow-list (never `*`) |

**Trusted proxies (HTTPS detection behind a load balancer).** The `SecurityHeaders`
middleware emits HSTS only when `$request->secure()` is true, and
`SESSION_SECURE_COOKIE` likewise depends on the request being seen as HTTPS. Behind a
TLS-terminating proxy/LB (e.g. Laravel Cloud), configure trusted proxies so the
`X-Forwarded-Proto` header is honored — otherwise both protections silently no-op.
In Laravel 12 this is set in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    // ...existing config...
    $middleware->trustProxies(at: '*'); // or the LB's specific CIDR(s)
});
```

This change is **deployment-specific** (the correct `at:` value depends on the host)
and is therefore left to the deploy step rather than committed with a guessed value.
