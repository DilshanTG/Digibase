# Digibase BaaS — Security & Architecture Audit

**Date:** 2026-02-09
**Auditor:** Claude Opus 4.6 (Adversarial Architecture Analysis)
**Scope:** Full codebase — Security, Performance, Data Integrity, Real-time, UI/JS

---

## TOP 3 CRITICAL RISKS (Project Killers)

### CRITICAL-1: SQL Playground Executes Arbitrary DDL/DML Without Protection
**File:** `app/Filament/Pages/SqlPlayground.php:83`
**Severity:** CRITICAL — Full database destruction possible

The `runQuery()` method calls `DB::unprepared($sql)` on any non-SELECT query with **zero keyword filtering**. A logged-in admin can execute `DROP TABLE users`, `DELETE FROM api_keys`, or even `UPDATE users SET password = '...' WHERE email = 'admin@...'`.

While the protected tables list exists as a property, it was **never checked** in the `runQuery()` method — only used in the import action.

**Fix applied:** Added comment stripping, protected table blocking, multi-statement blocking, and destructive keyword checks before `DB::unprepared()`.

---

### CRITICAL-2: UniverSheetWidget Leaks ANY Active API Key to the Browser
**File:** `app/Filament/Widgets/UniverSheetWidget.php:61`
**Severity:** CRITICAL — Secret key exposure

```php
$apiKey = ApiKey::where('is_active', true)->first()?->key ?? 'DEMO_KEY';
```

This grabs the **first active API key from the entire system** — potentially a `sk_` secret key belonging to a different user — and embeds it in the Filament page HTML as `apiToken`. Any admin user can inspect the page source and steal another user's secret key.

**Fix applied:** Scoped the query to `auth()->id()` so only the current user's own key is used.

---

### CRITICAL-3: Timing Attack on API Key Validation
**File:** `app/Http/Middleware/VerifyApiKey.php:32`
**Severity:** HIGH — Key enumeration via timing oracle

```php
$apiKey = ApiKey::where('key', $token)->first();
```

SQL `WHERE` clauses short-circuit on string comparison. An attacker can measure response times to determine how many leading characters of a key match, effectively brute-forcing the key byte-by-byte. With `sk_` + 32 chars of `Str::random()`, the entropy is reduced from 62^32 to 62×32 = ~1,984 attempts.

**Fix applied:** Iterate active keys with `hash_equals()` for constant-time comparison.

---

## FULL FINDINGS BY CATEGORY

---

### 1. Security & Auth Vulnerabilities

#### SEC-1: No Table-Scoping on API Keys (IDOR)
**File:** `app/Http/Middleware/VerifyApiKey.php`
**Severity:** HIGH

API keys grant access to **all** dynamic tables. A `pk_` key meant for the "products" table can read data from the "users_data" or "payments" table. There is no concept of "this key is allowed for table X only."

**Recommendation:** Add an optional `allowed_tables` JSON column to the `api_keys` table and check it in the middleware or controller.

#### SEC-2: `api_keys` Table Exposed via Database Explorer
**File:** `app/Http/Controllers/Api/DatabaseController.php:18-40`
**Severity:** HIGH

The `api_keys` table was missing from `$protectedTables`. An authenticated user could use the Database Explorer API (`GET /api/database/tables/api_keys/data`) to read all API keys in plaintext, including `sk_` secret keys belonging to other users.

**Fix applied:** Added `api_keys`, `db_config`, and `file_system_items` to the protected list.

#### SEC-3: Open Registration Without Rate Limit Bypass Protection
**File:** `app/Http/Controllers/Api/AuthController.php:15-35`
**Severity:** MEDIUM

Registration is throttled at `10,1` (10 per minute) but there's no CAPTCHA, email verification requirement, or account creation limit. An attacker can create unlimited accounts by rotating IPs.

**Recommendation:** Add email verification requirement and consider CAPTCHA for registration.

#### SEC-4: OAuth Token Leaked in URL Fragment
**File:** `app/Http/Controllers/Api/AuthController.php:206`
**Severity:** LOW-MEDIUM

```php
return redirect()->to(config('app.frontend_url', '/') . '#token=' . $token);
```

While URL fragments aren't sent in HTTP Referer headers, they are visible in browser history, JavaScript `window.location`, and browser extensions. Consider using a short-lived authorization code exchanged server-side instead.

#### SEC-5: SQL Injection in SQL Playground Import
**File:** `app/Filament/Pages/SqlPlayground.php:193`
**Severity:** MEDIUM (requires admin access)

```php
$fks = DB::select("PRAGMA foreign_key_list('$tableName')");
```

The `$tableName` comes from `LaravelSchema::getTableListing()` so it's not user-controlled in practice, but the pattern is dangerous and should use parameterized queries.

#### SEC-6: Webhook SSRF (Server-Side Request Forgery)
**File:** `app/Http/Controllers/Api/DynamicDataController.php:171-175`
**Severity:** MEDIUM

Webhooks can target any URL including internal network addresses (`http://localhost`, `http://169.254.169.254` for cloud metadata). There is no URL validation or blocklist for internal IPs.

**Recommendation:** Validate webhook URLs against a blocklist of private/internal IP ranges before dispatching.

---

### 2. Database & Schema Drift (SQLite Focus)

#### DB-1: SQLite Will Deadlock Under Univer Bulk Edits
**File:** `config/database.php:41`
**Severity:** HIGH

```php
'busy_timeout' => null,
'journal_mode' => null,
'synchronous' => null,
'transaction_mode' => 'DEFERRED',
```

With `busy_timeout` null (0ms), SQLite returns `SQLITE_BUSY` immediately on write contention. When 50 Univer cells are edited simultaneously, each fires a PUT request, each tries to write — SQLite will throw "database is locked" on all but the first.

Combined with `DEFERRED` transaction mode, reads that escalate to writes will deadlock against each other.

**Fix applied:** Set `busy_timeout: 5000`, `journal_mode: WAL`, `synchronous: NORMAL`, `transaction_mode: IMMEDIATE`.

WAL mode allows concurrent reads during writes. `busy_timeout: 5000` makes SQLite retry for 5 seconds before failing. `IMMEDIATE` transactions declare write-intent upfront, preventing deadlocks.

#### DB-2: Dynamic Column Addition Has No Rollback
**File:** `app/Http/Controllers/Api/DynamicModelController.php:389-432`
**Severity:** MEDIUM

The `generateAddColumnsMigration` method creates a migration file and runs it, but the migration's `down()` method is empty:
```php
public function down(): void {
    // Rollback not fully supported for dynamic additions yet
}
```

If the migration partially succeeds (e.g., adds 2 of 3 columns before failing), the DynamicField records are rolled back by the DB transaction, but the **actual columns remain in the database** — causing a schema drift between `dynamic_fields` and the real table.

**Recommendation:** The `down()` migration should drop the added columns. Also, verify column existence before creating DynamicField records.

#### DB-3: No Type Casting Before Database Insert
**File:** `app/Http/Controllers/Api/DynamicDataController.php:554-571`
**Severity:** MEDIUM

Only `json` and `boolean` types are explicitly cast. An `integer` field receiving the string `"abc"` passes through with no cast — SQLite will silently accept it (type affinity), but the data is now corrupted. Fields like `decimal`, `float`, `date`, `datetime`, and `time` are not cast at all.

**Recommendation:** Add explicit casting in the store/update data preparation loop:
```php
match ($field->type) {
    'integer' => (int) $value,
    'float', 'decimal' => (float) $value,
    'boolean' => (bool) $value,
    'json' => is_array($value) ? json_encode($value) : $value,
    default => $value,
};
```

---

### 3. Cache Audit ("Stale Memory")

#### CACHE-1: Cache Key Ignores Auth Context — Cross-User Data Leak
**File:** `app/Http/Traits/TurboCache.php:51-68`
**Severity:** HIGH

The cache key is built from `$tableName` + query params only. If User A (with RLS rule `auth.id == user_id`) fetches their data and it gets cached, User B hitting the same URL with the same params gets User A's cached data.

**Fix applied:** Added `auth('sanctum')->id()` and the API key ID to the cache key hash.

#### CACHE-2: File Cache Driver Flushes ENTIRE Cache on Any Mutation
**File:** `app/Observers/DynamicRecordObserver.php:95`
**Severity:** MEDIUM

```php
Cache::flush(); // Clears ALL cache — not just Digibase data
```

On shared hosting (the target deployment), this will flush Laravel's session cache, route cache, config cache, and any other cached data. Every single write to any dynamic table nukes everything.

**Recommendation:** For file-based cache, use a dedicated cache store for Digibase data:
```php
// config/cache.php
'digibase' => ['driver' => 'file', 'path' => storage_path('framework/cache/digibase')],
```
Then use `Cache::store('digibase')->flush()` instead of `Cache::flush()`.

#### CACHE-3: Race Condition Between Cache Clear and Cache Write
**Severity:** LOW

The sequence: (1) mutation clears cache, (2) concurrent GET starts, (3) GET re-caches stale data from before the mutation is **possible** but unlikely with a 5-minute TTL. The window is sub-millisecond. This is acceptable for a BaaS; if needed, add a short cache-lock or use versioned cache keys.

#### CACHE-4: `buildCacheKey` Doesn't Include Filter Params
**File:** `app/Http/Traits/TurboCache.php:53-61`
**Severity:** MEDIUM

The cache key includes `'filter'` but the controller uses individual field names as filter params (e.g., `?status=active`). These per-field filters are **not captured** in the cache key, meaning filtered and unfiltered results can collide.

**Fix applied:** The cache key now includes `direction` param. For full correctness, the cache key should hash all query params, not a whitelist.

---

### 4. Real-time & WebSocket

#### WS-1: Public Channel = Zero Authentication on WebSocket Data
**File:** `app/Events/ModelChanged.php:41`, `routes/channels.php:19`
**Severity:** HIGH

```php
new Channel("public-data.{$this->table}")  // NOT PrivateChannel
```

Any WebSocket client can subscribe to `public-data.users_data` or `public-data.payments` without any API key or auth token. The channel name is predictable (it's the table name). This means:
- All created/updated record data is broadcast in real-time to anyone listening
- Hidden fields (`is_hidden`) are included in the broadcast
- Deleted record IDs are leaked

**Recommendation:** Switch to `PrivateChannel` and validate the API key in the channel authorization callback:
```php
Broadcast::channel('data.{tableName}', function ($user, $tableName) {
    // Verify user has an active API key with read scope
    return ApiKey::where('user_id', $user->id)
        ->where('is_active', true)
        ->exists();
});
```

#### WS-2: 1,000 Univer Edits = 1,000 Broadcast Events
**File:** `app/Observers/DynamicRecordObserver.php`
**Severity:** MEDIUM

Each cell edit in Univer fires a PUT request, which triggers the observer, which fires a `ShouldBroadcastNow` event. With 1,000 rows edited, that's 1,000 synchronous broadcast dispatches **in the request thread** (not queued).

Combined with the debounce fix on the JS side, this is reduced. But for true bulk operations, consider:
- Using `ShouldBroadcast` (queued) instead of `ShouldBroadcastNow`
- Adding event batching: collect events for 100ms then send a single "batch_updated" event

#### WS-3: Broadcast Includes Full Record Data Including Sensitive Fields
**File:** `app/Observers/DynamicRecordObserver.php:34,57`
**Severity:** MEDIUM

```php
event(new ModelChanged($tableName, 'created', $record->toArray()));
```

`$record->toArray()` includes **all** columns, including those marked `is_hidden` in the DynamicModel fields config. Password hashes, encrypted fields, and hidden fields are all broadcast.

**Recommendation:** Filter hidden fields before broadcasting, or broadcast only the record ID and let clients fetch the filtered data.

---

### 5. UI/UX & JS Integration (Univer Adapter)

#### UI-1: No Error Revert on Failed Save
**File:** `resources/js/univer-adapter.js:257-259`
**Severity:** MEDIUM

When the PUT request fails (500, 422 validation error, network timeout), the Univer grid shows the new value but the database has the old value. The user thinks it saved. This causes silent data desync.

**Fix applied:** Added error handling that reverts the local data model and shows an alert to the user.

#### UI-2: No Debounce on Cell Edits — Request Flood
**File:** `resources/js/univer-adapter.js:202-211`
**Severity:** MEDIUM

Every keystroke in a cell fires `set-range-values`, which immediately fires a PUT request. Typing "hello" sends 5 PUT requests in rapid succession. This floods the API, the SQLite database, and the Reverb WebSocket with events.

**Fix applied:** Added 400ms debounce per cell. Only the final value is sent after the user stops typing.

#### UI-3: Z-Index Fix is Fragile
**File:** `resources/js/univer-adapter.js:48-59`
**Severity:** LOW

The `z-index: 9999 !important` override will conflict with Filament modals (which use z-index 50-9999 range). If Filament opens a confirmation dialog while Univer is visible, the Univer editor popup may overlay the modal.

**Recommendation:** Use a Filament-aware z-index (e.g., 40) for Univer, and 50+ only for the active cell editor.

#### UI-4: Real-time Conflicts — No Conflict Resolution
**Severity:** MEDIUM (Architectural)

If User A and User B edit the same cell simultaneously:
1. User A saves → broadcast → User B's Univer doesn't process the incoming event (no listener)
2. User B saves → overwrites User A's value (last-write-wins)
3. Neither user knows a conflict occurred

This is acceptable for a v1 BaaS, but should be documented as a known limitation.

---

## PERFORMANCE RECOMMENDATIONS

### PERF-1: `recordUsage()` Writes to DB on Every API Request
**File:** `app/Models/ApiKey.php:109-112`

Every single API call triggers `$this->update(['last_used_at' => now()])`, which is a full UPDATE query + Eloquent model hydration. Under 1,000 concurrent users, that's 1,000 extra writes per second just for usage tracking.

**Fix applied:** Throttled to once per minute per key using cache.

### PERF-2: Observer Cache Flush on File Driver Destroys Everything
As noted in CACHE-2, on shared hosting with file cache, every mutation nukes the entire application cache.

### PERF-3: `ShouldBroadcastNow` Blocks the Request Thread
The `ModelChanged` event implements `ShouldBroadcastNow`, meaning the HTTP response waits for the Reverb broadcast to complete. Under high load, this adds latency to every write operation.

**Recommendation:** Switch to `ShouldBroadcast` (queued) for production.

---

## SUMMARY TABLE

| # | Finding | Severity | Fixed? |
|---|---------|----------|--------|
| CRITICAL-1 | SQL Playground arbitrary execution | CRITICAL | YES |
| CRITICAL-2 | Univer widget leaks any API key | CRITICAL | YES |
| CRITICAL-3 | Timing attack on API key lookup | HIGH | YES |
| SEC-2 | `api_keys` table exposed in DB Explorer | HIGH | YES |
| CACHE-1 | Cache key ignores auth (cross-user leak) | HIGH | YES |
| DB-1 | SQLite deadlock under concurrent writes | HIGH | YES |
| WS-1 | Public WebSocket channel (no auth) | HIGH | Documented |
| SEC-1 | No table-scoping on API keys (IDOR) | HIGH | Documented |
| DB-2 | Dynamic column addition no rollback | MEDIUM | Documented |
| DB-3 | No type casting before insert | MEDIUM | Documented |
| CACHE-2 | File cache flush nukes everything | MEDIUM | Documented |
| WS-2 | 1000 edits = 1000 broadcast events | MEDIUM | Partially (debounce) |
| WS-3 | Broadcast includes hidden fields | MEDIUM | Documented |
| SEC-3 | Open registration, no verification | MEDIUM | Documented |
| SEC-6 | Webhook SSRF | MEDIUM | Documented |
| UI-1 | No error revert on failed save | MEDIUM | YES |
| UI-2 | No debounce on cell edits | MEDIUM | YES |
| PERF-1 | recordUsage writes per-request | MEDIUM | YES |
| SEC-4 | OAuth token in URL fragment | LOW-MEDIUM | Documented |
| SEC-5 | SQL injection in PRAGMA call | MEDIUM | Documented |
| CACHE-3 | Race condition (theoretical) | LOW | Documented |
| CACHE-4 | Filter params not in cache key | MEDIUM | Partially |
| UI-3 | Z-index conflict with Filament | LOW | Documented |
| UI-4 | No real-time conflict resolution | MEDIUM | Documented |
