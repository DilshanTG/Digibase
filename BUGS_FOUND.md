# BUGS_FOUND.md — Digibase Deep Hunt Report

Scanned: `CoreDataController`, `DynamicRecordObserver`, `UniverSheetWidget`, `VerifyApiKey`, `TurboCache`, `routes/api.php`, `ModelChanged`

---

## 1. VerifyApiKey loads ALL active keys on every request — **Severity: HIGH**

**File:** `app/Http/Middleware/VerifyApiKey.php:36`

```php
$candidates = ApiKey::where('is_active', true)->get();
foreach ($candidates as $candidate) {
    if (hash_equals($candidate->key, $token)) {
```

**Problem:** Every single API request loads the *entire* `api_keys` table into memory to do a constant-time comparison. With 1,000 API keys, that's 1,000 Eloquent model hydrations per request. This is an O(n) full-table scan disguised as security.

**Impact:** Memory leak under load. Latency scales linearly with key count.

**Fix:** Store a SHA-256 hash of the key in a `key_hash` indexed column. Look up by hash directly: `ApiKey::where('key_hash', hash('sha256', $token))->first()`. Then `hash_equals()` the raw key as a final verification. O(1) lookup, still constant-time safe.

---

## 2. DynamicRecordObserver fires N+1 query on every write — **Severity: HIGH**

**File:** `app/Observers/DynamicRecordObserver.php:64`

```php
$model = DynamicModel::where('table_name', $tableName)
    ->with('fields')
    ->first();
```

**Problem:** `filterHiddenFields()` is called on `created()` and `updated()` events. Each call makes a fresh database query to load the DynamicModel + all its fields. On a bulk insert of 100 rows, this fires 100 identical queries.

**Impact:** Massive query overhead on bulk operations (the exact UniverSheetWidget scenario).

**Fix:** Cache the hidden fields per table in the dedicated `digibase` store with a 60-second TTL:

```php
$hiddenFields = Cache::store('digibase')->remember(
    "hidden_fields:{$tableName}", 60,
    fn() => DynamicModel::where('table_name', $tableName)->with('fields')->first()
        ?->fields->where('is_hidden', true)->pluck('name')->toArray() ?? []
);
```

---

## 3. UniverSheetWidget `saveUrl` pointed to dead legacy route — **Severity: HIGH** (FIXED)

**File:** `app/Filament/Widgets/UniverSheetWidget.php:71`

```php
'saveUrl' => url('/api/data/' . $dynamicModel->table_name),
```

**Problem:** The v1 routes are at `/api/v1/data/{table}` but the widget was still generating URLs for the old `/api/data/{table}` legacy path. Legacy routes use a different controller (`DynamicDataController`), meaning saves from the Univer spreadsheet hit the wrong controller — one that lacks the Iron Dome, Turbo Cache, and Type-Safe Casting systems.

**Impact:** Admin spreadsheet edits bypass the new unified API entirely. No transaction wrapping, no type casting, no cache invalidation on the v1 store.

**Status:** Fixed in this PR — URL now points to `/api/v1/data/`.

---

## 4. `resolveRelationUsing` leaks across requests (global state pollution) — **Severity: HIGH**

**File:** `app/Http/Controllers/Api/CoreDataController.php:393` and `:514`

```php
DynamicRecord::resolveRelationUsing($relationName, function ($instance) use ($relDef) { ... });
```

**Problem:** `resolveRelationUsing()` registers the closure on the *class* (static), not the instance. In a long-running process (Octane, Swoole, queue workers), the first request's relation definitions persist forever. If two tables both have a relation named `comments` but with different foreign keys, the second table will use the first table's definition.

**Impact:** Wrong data returned silently. Data from Table A's relation bleeds into Table B's API response.

**Fix:** Namespace the relation name with the table name (e.g., `{tableName}_{relationName}`) or clear resolved relations at the start of each request via middleware.

---

## 5. Webhook sensitive-field stripping has redundant inner loop — **Severity: MEDIUM**

**File:** `app/Http/Controllers/Api/CoreDataController.php:153-162`

```php
foreach ($sensitiveKeys as $key) {
    if (isset($recordData[$key])) {
        unset($recordData[$key]);
    }
    foreach ($recordData as $k => $v) {          // ← This runs on every iteration of the outer loop
        if (Str::contains(strtolower($k), $sensitiveKeys)) {
            unset($recordData[$k]);
        }
    }
}
```

**Problem:** The inner `foreach ($recordData ...)` loop runs 7 times (once per `$sensitiveKeys` entry) even though a single pass would suffice. It also calls `Str::contains` with the full `$sensitiveKeys` array every time, doing redundant work.

**Impact:** 7× more iterations than necessary. Low severity alone, but compounds under high webhook volume.

**Fix:** Split into two passes: one exact-key check, one pattern-match pass:

```php
$recordData = array_filter($recordData, function ($v, $k) use ($sensitiveKeys) {
    $lower = strtolower($k);
    foreach ($sensitiveKeys as $pattern) {
        if ($lower === $pattern || str_contains($lower, $pattern)) return false;
    }
    return true;
}, ARRAY_FILTER_USE_BOTH);
```

---

## 6. `ShouldBroadcastNow` blocks the response thread — **Severity: MEDIUM**

**File:** `app/Events/ModelChanged.php:20`

```php
class ModelChanged implements ShouldBroadcastNow
```

**Problem:** `ShouldBroadcastNow` dispatches the broadcast synchronously on the current request. If the broadcast driver (Pusher, Ably, Redis) is slow or times out, the entire API response is delayed.

**Impact:** Every create/update/delete request pays the broadcasting latency. Under Pusher rate limits, this can add 500ms+ to writes.

**Fix:** Switch to `ShouldBroadcast` (queued) instead of `ShouldBroadcastNow`. This pushes the broadcast to a queue worker and frees the HTTP response.

---

## 7. UniverSheetWidget fetches ALL records with no pagination — **Severity: MEDIUM**

**File:** `app/Filament/Widgets/UniverSheetWidget.php:54`

```php
$records = $modelClass->get()->toArray();
```

**Problem:** `->get()` loads every single row from the dynamic table into memory. A table with 100,000 rows will hydrate 100,000 Eloquent models, convert them all to arrays, then serialize them into the Blade view.

**Impact:** PHP memory exhaustion (`Allowed memory size exhausted`) on large tables. The widget becomes a denial-of-service vector against the admin panel.

**Fix:** Add a sensible limit (e.g., `->limit(5000)->get()`) or implement chunked lazy loading on the frontend.

---

## 8. `makeHidden` called on wrong object type — **Severity: LOW**

**File:** `app/Http/Controllers/Api/CoreDataController.php:468`

```php
$data->getCollection()->each(function ($item) use ($hiddenFields) {
    if (property_exists($item, 'makeHidden')) {
        $item->makeHidden($hiddenFields);
    }
});
```

**Problem:** `makeHidden` is a *method*, not a *property*. `property_exists($item, 'makeHidden')` will always return `false` on Eloquent models because `makeHidden` is a method inherited from the `HasAttributes` trait. The hidden fields are never actually hidden in list responses.

**Impact:** Hidden/sensitive fields are exposed in the `index` endpoint's response. Security issue if fields like `password_hash` are marked `is_hidden`.

**Fix:** Replace `property_exists` with `method_exists`:

```php
if (method_exists($item, 'makeHidden')) {
    $item->makeHidden($hiddenFields);
}
```

---

## 9. Schema endpoint route shadowed by show endpoint — **Severity: LOW**

**File:** `routes/api.php:53-54`

```php
Route::get('/data/{tableName}/{id}', [..., 'show']);
Route::get('/data/{tableName}/schema', [..., 'schema']);
```

**Problem:** The `show` route (`/data/{tableName}/{id}`) is registered *before* the `schema` route (`/data/{tableName}/schema`). Laravel matches routes top-down. A request to `/data/users/schema` will match the `show` route with `$id = "schema"`, then fail with a 404 when it tries to `find("schema")` as an integer.

**Impact:** The schema introspection endpoint is completely unreachable.

**Fix:** Move the schema route *above* the show route:

```php
Route::get('/data/{tableName}/schema', [..., 'schema']);
Route::get('/data/{tableName}/{id}', [..., 'show']);
```

---

## 10. RLS rule parser has operator precedence bug — **Severity: LOW**

**File:** `app/Http/Controllers/Api/CoreDataController.php:108-122`

```php
if (str_contains($rule, '&&')) { ... }
if (str_contains($rule, '||')) { ... }
```

**Problem:** `&&` and `||` are checked with simple `str_contains` and `explode`. A rule like `auth.id == user_id || auth.id == admin_id && role == 'admin'` would be split on `&&` first, which breaks the intended `||` semantics. Standard boolean precedence (`&&` binds tighter than `||`) is not respected.

**Impact:** Complex RLS rules with mixed operators may grant or deny access incorrectly.

**Fix:** Either enforce single-operator rules (document the limitation) or implement proper recursive descent parsing with precedence levels.
