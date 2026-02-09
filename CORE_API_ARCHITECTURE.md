# 🏗️ Core API Engine Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENT APPLICATIONS                          │
│  (digibase.js SDK, Mobile Apps, Web Apps, Third-party Services)    │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ HTTP Requests
                             │ (Authorization: Bearer sk_xxx)
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          API GATEWAY                                 │
│                      Laravel Router (api.php)                        │
├─────────────────────────────────────────────────────────────────────┤
│  Legacy API: /api/data/{table}        (Backward Compatible)         │
│  New v1 API: /api/v1/data/{table}     (Recommended)                 │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Middleware Pipeline
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      MIDDLEWARE LAYER                                │
├─────────────────────────────────────────────────────────────────────┤
│  1. VerifyApiKey (Iron Dome)                                        │
│     ├─ Validate API key (pk_/sk_)                                   │
│     ├─ Check scopes (read, write, delete)                           │
│     ├─ Verify table access (allowed_tables)                         │
│     └─ Record usage tracking                                        │
│                                                                      │
│  2. ApiRateLimiter                                                  │
│     ├─ Read rate_limit from api_keys table                          │
│     ├─ Enforce per-key limits                                       │
│     ├─ Add rate limit headers                                       │
│     └─ Return 429 if exceeded                                       │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Authorized Request
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    CORE DATA CONTROLLER                              │
│              (app/Http/Controllers/Api/CoreDataController.php)       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌────────────────────────────────────────────────────────────┐   │
│  │  REQUEST PROCESSING                                         │   │
│  ├────────────────────────────────────────────────────────────┤   │
│  │  1. Get DynamicModel by table name                         │   │
│  │  2. Validate RLS rules (list_rule, create_rule, etc.)      │   │
│  │  3. Build validation rules (Schema Doctor)                 │   │
│  │  4. Execute operation in transaction                        │   │
│  └────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────┐   │
│  │  CRUD OPERATIONS                                            │   │
│  ├────────────────────────────────────────────────────────────┤   │
│  │  • index()   - List records (with cache)                   │   │
│  │  • show()    - Get single record                           │   │
│  │  • store()   - Create record (transaction + type-safe)     │   │
│  │  • update()  - Update record (transaction + type-safe)     │   │
│  │  • destroy() - Delete record (transaction)                 │   │
│  │  • schema()  - Get model schema                            │   │
│  └────────────────────────────────────────────────────────────┘   │
│                                                                      │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Integrations
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      CORE SYSTEMS                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  🛡️ IRON DOME (Security)                                           │
│  ├─ API Key validation                                              │
│  ├─ Scope-based permissions                                         │
│  ├─ Table-level access control                                      │
│  └─ Usage tracking                                                  │
│                                                                      │
│  🩺 SCHEMA DOCTOR (Validation)                                      │
│  ├─ Dynamic validation rules                                        │
│  ├─ Type-based validation                                           │
│  ├─ Required field enforcement                                      │
│  └─ Unique constraint handling                                      │
│                                                                      │
│  ⚡ TURBO CACHE (Performance)                                       │
│  ├─ Automatic caching for GET                                       │
│  ├─ Cache invalidation on mutations                                 │
│  ├─ Request-aware cache keys                                        │
│  └─ Configurable TTL                                                │
│                                                                      │
│  📡 LIVE WIRE (Real-time)                                           │
│  ├─ Event broadcasting                                              │
│  ├─ Webhook triggering                                              │
│  ├─ ModelActivity events                                            │
│  └─ Private channel support                                         │
│                                                                      │
│  🔒 TRANSACTION WRAPPER (Stability)                                 │
│  ├─ All mutations in DB transactions                                │
│  ├─ Automatic rollback on errors                                    │
│  ├─ Prevents partial updates                                        │
│  └─ Error logging with context                                      │
│                                                                      │
│  🎯 TYPE-SAFE CASTING (Data Integrity)                              │
│  ├─ Integer/bigint → (int)                                          │
│  ├─ Float/decimal/money → (float)                                   │
│  ├─ Boolean/checkbox → filter_var()                                 │
│  ├─ JSON/array → json_encode()                                      │
│  └─ Date/time → date formatting                                     │
│                                                                      │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Database Operations
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      DATABASE LAYER                                  │
├─────────────────────────────────────────────────────────────────────┤
│  SQLite Database (WAL Mode)                                         │
│  ├─ Dynamic tables (created by users)                               │
│  ├─ System tables (users, api_keys, etc.)                           │
│  ├─ Optimized with indexes                                          │
│  └─ Transaction support                                             │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Request Flow Diagram

### GET Request (List Records)

```
Client Request
    │
    ├─> VerifyApiKey Middleware
    │   ├─ Validate API key
    │   ├─ Check 'read' scope
    │   └─ Verify table access
    │
    ├─> ApiRateLimiter Middleware
    │   ├─ Check rate limit
    │   └─ Add rate limit headers
    │
    ├─> CoreDataController::index()
    │   ├─ Get DynamicModel
    │   ├─ Validate list_rule (RLS)
    │   ├─ Check Turbo Cache
    │   │   ├─ Cache HIT → Return cached data
    │   │   └─ Cache MISS → Continue
    │   ├─ Build query with filters
    │   ├─ Execute query
    │   ├─ Hide hidden fields
    │   ├─ Store in cache
    │   └─ Return JSON response
    │
    └─> Response with rate limit headers
```

### POST Request (Create Record)

```
Client Request
    │
    ├─> VerifyApiKey Middleware
    │   ├─ Validate API key
    │   ├─ Check 'write' scope
    │   └─ Verify table access
    │
    ├─> ApiRateLimiter Middleware
    │   ├─ Check rate limit
    │   └─ Add rate limit headers
    │
    ├─> CoreDataController::store()
    │   ├─ Get DynamicModel
    │   ├─ Validate create_rule (RLS)
    │   ├─ Build validation rules (Schema Doctor)
    │   ├─ Validate request data
    │   │   └─ Return 422 if invalid
    │   ├─> START TRANSACTION
    │   │   ├─ Cast values to correct types
    │   │   ├─ Create record
    │   │   ├─ Save to database
    │   │   └─ COMMIT
    │   ├─ Invalidate Turbo Cache
    │   ├─ Dispatch ModelActivity event
    │   ├─ Trigger webhooks (async)
    │   └─ Return 201 with record data
    │
    └─> Response with rate limit headers
```

### PUT Request (Update Record)

```
Client Request
    │
    ├─> VerifyApiKey Middleware
    │   ├─ Validate API key
    │   ├─ Check 'write' scope
    │   └─ Verify table access
    │
    ├─> ApiRateLimiter Middleware
    │   ├─ Check rate limit
    │   └─ Add rate limit headers
    │
    ├─> CoreDataController::update()
    │   ├─ Get DynamicModel
    │   ├─ Find existing record
    │   │   └─ Return 404 if not found
    │   ├─ Validate update_rule (RLS)
    │   │   └─ Return 403 if denied
    │   ├─ Build validation rules (Schema Doctor)
    │   ├─ Validate request data
    │   │   └─ Return 422 if invalid
    │   ├─> START TRANSACTION
    │   │   ├─ Cast values to correct types
    │   │   ├─ Update record
    │   │   ├─ Save to database
    │   │   └─ COMMIT
    │   ├─ Invalidate Turbo Cache
    │   ├─ Dispatch ModelActivity event
    │   ├─ Trigger webhooks (async)
    │   └─ Return 200 with updated record
    │
    └─> Response with rate limit headers
```

### DELETE Request (Delete Record)

```
Client Request
    │
    ├─> VerifyApiKey Middleware
    │   ├─ Validate API key
    │   ├─ Check 'delete' scope
    │   └─ Verify table access
    │
    ├─> ApiRateLimiter Middleware
    │   ├─ Check rate limit
    │   └─ Add rate limit headers
    │
    ├─> CoreDataController::destroy()
    │   ├─ Get DynamicModel
    │   ├─ Find existing record
    │   │   └─ Return 404 if not found
    │   ├─ Validate delete_rule (RLS)
    │   │   └─ Return 403 if denied
    │   ├─> START TRANSACTION
    │   │   ├─ Soft delete or hard delete
    │   │   └─ COMMIT
    │   ├─ Invalidate Turbo Cache
    │   ├─ Dispatch ModelActivity event
    │   ├─ Trigger webhooks (async)
    │   └─ Return 200 with success message
    │
    └─> Response with rate limit headers
```

---

## Error Handling Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                      ERROR SCENARIOS                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Missing API Key                                                │
│  └─> 401 Unauthorized                                           │
│      └─> { error_code: "MISSING_API_KEY" }                      │
│                                                                  │
│  Invalid API Key                                                │
│  └─> 401 Unauthorized                                           │
│      └─> { error_code: "INVALID_API_KEY" }                      │
│                                                                  │
│  Insufficient Scope                                             │
│  └─> 403 Forbidden                                              │
│      └─> { error_code: "INSUFFICIENT_SCOPE" }                   │
│                                                                  │
│  Table Access Denied                                            │
│  └─> 403 Forbidden                                              │
│      └─> { error_code: "TABLE_ACCESS_DENIED" }                  │
│                                                                  │
│  RLS Rule Denied                                                │
│  └─> 403 Forbidden                                              │
│      └─> { message: "Access denied by security rules" }         │
│                                                                  │
│  Model Not Found                                                │
│  └─> 404 Not Found                                              │
│      └─> { message: "Model not found" }                         │
│                                                                  │
│  Record Not Found                                               │
│  └─> 404 Not Found                                              │
│      └─> { message: "Record not found" }                        │
│                                                                  │
│  Validation Failed                                              │
│  └─> 422 Unprocessable Entity                                   │
│      └─> { message: "Validation failed", errors: {...} }        │
│                                                                  │
│  Rate Limit Exceeded                                            │
│  └─> 429 Too Many Requests                                      │
│      └─> { message: "Too many requests", retry_after: 60 }      │
│                                                                  │
│  Server Error                                                   │
│  └─> 500 Internal Server Error                                  │
│      ├─> Log error with context                                 │
│      ├─> Rollback transaction                                   │
│      └─> { message: "Failed to create record" }                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Data Flow: Type-Safe Casting

```
Client Request Data
    │
    ├─ { "name": "Product", "price": "29.99", "quantity": "10", "is_active": "1" }
    │
    ▼
Schema Doctor Validation
    │
    ├─ name: string ✓
    ├─ price: numeric ✓
    ├─ quantity: integer ✓
    └─ is_active: boolean ✓
    │
    ▼
Type-Safe Casting
    │
    ├─ name: "Product" (string)
    ├─ price: 29.99 (float)
    ├─ quantity: 10 (int)
    └─ is_active: true (boolean)
    │
    ▼
Database Write
    │
    └─ SQLite stores with correct types
```

---

## Cache Flow

```
GET Request
    │
    ├─> Check Turbo Cache
    │   ├─ Key: "table:products:params:hash"
    │   │
    │   ├─ Cache HIT
    │   │   └─> Return cached data (fast!)
    │   │
    │   └─ Cache MISS
    │       ├─> Execute database query
    │       ├─> Store result in cache
    │       └─> Return data
    │
    └─> Response

POST/PUT/DELETE Request
    │
    ├─> Execute mutation
    │
    └─> Invalidate cache
        └─> Clear all cache keys for this table
```

---

## Rate Limiting Flow

```
Request
    │
    ├─> ApiRateLimiter Middleware
    │   │
    │   ├─ Get API key from request
    │   ├─ Read rate_limit from api_keys table
    │   │   └─ Default: 60 req/min
    │   │
    │   ├─ Check RateLimiter
    │   │   ├─ Key: "api:{api_key_id}"
    │   │   ├─ Max: {rate_limit}
    │   │   └─ Decay: 1 minute
    │   │
    │   ├─ Too Many Attempts?
    │   │   ├─ YES → Return 429
    │   │   │   └─ Headers: X-RateLimit-*, Retry-After
    │   │   │
    │   │   └─ NO → Continue
    │   │       ├─ Hit rate limiter
    │   │       └─ Add rate limit headers
    │   │
    │   └─> Next middleware
    │
    └─> Controller
```

---

## Webhook Flow

```
CRUD Operation Complete
    │
    ├─> triggerWebhooks()
    │   │
    │   ├─ Find active webhooks for this model
    │   │
    │   ├─ For each webhook:
    │   │   ├─ Check if event matches (created, updated, deleted)
    │   │   ├─ Validate URL (SSRF protection)
    │   │   ├─ Remove sensitive fields
    │   │   ├─ Build payload
    │   │   ├─ Generate signature
    │   │   ├─ Send HTTP POST (async)
    │   │   │   ├─ Success → recordSuccess()
    │   │   │   └─ Failure → recordFailure()
    │   │   └─ Log result
    │   │
    │   └─> Continue (non-blocking)
    │
    └─> Return response to client
```

---

## Transaction Flow

```
Mutation Request (POST/PUT/DELETE)
    │
    ├─> executeInTransaction()
    │   │
    │   ├─> BEGIN TRANSACTION
    │   │   │
    │   │   ├─ Cast values to correct types
    │   │   ├─ Create/Update/Delete record
    │   │   ├─ Save to database
    │   │   │
    │   │   ├─ Success?
    │   │   │   ├─ YES → COMMIT
    │   │   │   │   └─> Return record
    │   │   │   │
    │   │   │   └─ NO → ROLLBACK
    │   │   │       ├─> Log error
    │   │   │       └─> Throw exception
    │   │   │
    │   │   └─> END TRANSACTION
    │   │
    │   └─> Return result
    │
    └─> Continue with events/webhooks
```

---

## System Integration Map

```
┌─────────────────────────────────────────────────────────────────┐
│                    CORE API ENGINE                               │
│                  (CoreDataController)                            │
└───────────────────────┬─────────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        │               │               │
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│  Iron Dome   │ │Schema Doctor │ │ Turbo Cache  │
│  (Security)  │ │ (Validation) │ │(Performance) │
└──────────────┘ └──────────────┘ └──────────────┘
        │               │               │
        └───────────────┼───────────────┘
                        │
        ┌───────────────┼───────────────┐
        │               │               │
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│  Live Wire   │ │ Transaction  │ │  Type-Safe   │
│ (Real-time)  │ │   Wrapper    │ │   Casting    │
└──────────────┘ └──────────────┘ └──────────────┘
        │               │               │
        └───────────────┼───────────────┘
                        │
                        ▼
                ┌──────────────┐
                │   Database   │
                │   (SQLite)   │
                └──────────────┘
```

---

**The Core API Engine is a unified, production-ready system that seamlessly integrates all Digibase core features! 🚀**
