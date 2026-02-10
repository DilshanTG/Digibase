# BUGS_FOUND.md — Digibase Deep Hunt Report

Scanned: `CoreDataController`, `DynamicRecordObserver`, `UniverSheetWidget`, `VerifyApiKey`, `TurboCache`, `routes/api.php`, `ModelChanged`

---

## 1. VerifyApiKey loads ALL active keys on every request — **Severity: HIGH** (FIXED)

**File:** `app/Http/Middleware/VerifyApiKey.php:36`

**Status:** Fixed. Implemented specific `key_hash` column and O(1) lookup logic.

---

## 2. DynamicRecordObserver fires N+1 query on every write — **Severity: HIGH** (FIXED)

**File:** `app/Observers/DynamicRecordObserver.php:64`

**Status:** Fixed. Implemented Two-Tier Caching (Static + Cache Store) for hidden fields.

---

## 3. UniverSheetWidget `saveUrl` pointed to dead legacy route — **Severity: HIGH** (FIXED)

**File:** `app/Filament/Widgets/UniverSheetWidget.php:71`

**Status:** Fixed. URL now points to `/api/v1/data/`.

---

## 4. `resolveRelationUsing` leaks across requests (global state pollution) — **Severity: HIGH** (FIXED)

**File:** `app/Http/Controllers/Api/CoreDataController.php:393` and `:514`

**Status:** Fixed. Relations are now namespaced with the table name (e.g., `{tableName}_{relationName}`).

---

## 5. Webhook sensitive-field stripping has redundant inner loop — **Severity: MEDIUM** (FIXED)

**File:** `app/Http/Controllers/Api/CoreDataController.php:153-162`

**Status:** Fixed. Replaced nested loop with optimized `array_filter`.

---

## 6. `ShouldBroadcastNow` blocks the response thread — **Severity: MEDIUM** (FIXED)

**File:** `app/Events/ModelChanged.php:20`

**Status:** Fixed. Switched to `ShouldBroadcast` (queued).

---

## 7. UniverSheetWidget fetches ALL records with no pagination — **Severity: MEDIUM** (FIXED)

**File:** `app/Filament/Widgets/UniverSheetWidget.php:54`

**Status:** Fixed. Added `->limit(1000)` to prevent memory exhaustion.

---

## 8. `makeHidden` called on wrong object type — **Severity: LOW** (FIXED)

**File:** `app/Http/Controllers/Api/CoreDataController.php:468`

**Status:** Fixed. Using `method_exists` instead of `property_exists`.

---

## 9. Schema endpoint route shadowed by show endpoint — **Severity: LOW** (FIXED)

**File:** `routes/api.php:53-54`

**Status:** Fixed. Route ordering verified (Schema comes before ID wildcard).

---

## 10. RLS rule parser has operator precedence bug — **Severity: LOW** (WONTFIX / DOCUMENTED)

**Status:** Documented limitation. Use single operator type per rule for now.
