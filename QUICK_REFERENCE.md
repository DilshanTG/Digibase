# 🚀 Core API Engine - Quick Reference

## 📋 Quick Start

### Get API Key
```bash
php artisan tinker
>>> App\Models\ApiKey::first()->key
```

### Test Endpoint
```bash
curl -H "Authorization: Bearer YOUR_KEY" \
  http://127.0.0.1:8001/api/v1/data/YOUR_TABLE
```

---

## 🔗 API Endpoints

### v1 API (Recommended)
```
GET    /api/v1/data/{table}          List records
POST   /api/v1/data/{table}          Create record
GET    /api/v1/data/{table}/{id}     Get record
PUT    /api/v1/data/{table}/{id}     Update record
DELETE /api/v1/data/{table}/{id}     Delete record
GET    /api/v1/data/{table}/schema   Get schema
```

### Legacy API (Backward Compatible)
```
GET    /api/data/{table}          List records
POST   /api/data/{table}          Create record
GET    /api/data/{table}/{id}     Get record
PUT    /api/data/{table}/{id}     Update record
DELETE /api/data/{table}/{id}     Delete record
GET    /api/data/{table}/schema   Get schema
```

---

## 🔐 Authentication

### Headers
```
Authorization: Bearer sk_your_secret_key_here
Content-Type: application/json
```

### API Key Types
- `pk_...` - Public key (read-only)
- `sk_...` - Secret key (full access)

### Scopes
- `read` - GET operations
- `write` - POST, PUT operations
- `delete` - DELETE operations
- `*` - All operations

---

## 📊 Request Examples

### List Records
```bash
curl -H "Authorization: Bearer sk_xxx" \
  "http://127.0.0.1:8001/api/v1/data/products?per_page=20&sort=created_at&direction=desc"
```

### Create Record
```bash
curl -X POST \
  -H "Authorization: Bearer sk_xxx" \
  -H "Content-Type: application/json" \
  -d '{"name":"Product","price":29.99}' \
  http://127.0.0.1:8001/api/v1/data/products
```

### Update Record
```bash
curl -X PUT \
  -H "Authorization: Bearer sk_xxx" \
  -H "Content-Type: application/json" \
  -d '{"price":39.99}' \
  http://127.0.0.1:8001/api/v1/data/products/1
```

### Delete Record
```bash
curl -X DELETE \
  -H "Authorization: Bearer sk_xxx" \
  http://127.0.0.1:8001/api/v1/data/products/1
```

### Get Schema
```bash
curl -H "Authorization: Bearer sk_xxx" \
  http://127.0.0.1:8001/api/v1/data/products/schema
```

---

## 🎯 Query Parameters

### Pagination
```
?per_page=20          Records per page (max 100)
?page=2               Page number
```

### Sorting
```
?sort=created_at      Sort field
?direction=desc       Sort direction (asc/desc)
```

### Filtering
```
?name=Product         Filter by field value
?price=29.99          Filter by exact match
```

### Search
```
?search=keyword       Search in searchable fields
```

### Relations
```
?include=category,tags    Include relationships
```

---

## 📈 Response Format

### Success (200/201)
```json
{
  "data": {
    "id": 1,
    "name": "Product",
    "price": 29.99,
    "created_at": "2024-01-01T00:00:00Z"
  }
}
```

### List (200)
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

### Error (4xx/5xx)
```json
{
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."]
  }
}
```

---

## 🚦 Rate Limiting

### Headers
```
X-RateLimit-Limit: 60          Max requests per minute
X-RateLimit-Remaining: 59      Remaining requests
X-RateLimit-Reset: 1234567890  Reset timestamp
```

### Rate Limit Exceeded (429)
```json
{
  "message": "Too many requests. Please slow down.",
  "retry_after": 60,
  "limit": 60,
  "remaining": 0
}
```

### Configure Rate Limit
```sql
UPDATE api_keys SET rate_limit = 100 WHERE id = 1;
```

---

## ⚠️ Error Codes

| Code | Status | Description |
|------|--------|-------------|
| `MISSING_API_KEY` | 401 | No API key provided |
| `INVALID_API_KEY` | 401 | Invalid or expired key |
| `INSUFFICIENT_SCOPE` | 403 | Missing required scope |
| `METHOD_NOT_ALLOWED` | 403 | Scope doesn't allow method |
| `TABLE_ACCESS_DENIED` | 403 | Table not in allowed_tables |
| - | 404 | Model or record not found |
| - | 422 | Validation failed |
| - | 429 | Rate limit exceeded |
| - | 500 | Server error |

---

## 🔧 Configuration

### API Key Settings
```php
// In database: api_keys table
[
    'name' => 'My API Key',
    'key' => 'sk_...',
    'type' => 'secret',
    'scopes' => ['read', 'write', 'delete'],
    'allowed_tables' => null,  // null = all tables
    'rate_limit' => 60,        // requests per minute
    'is_active' => true,
    'expires_at' => null,      // null = never expires
]
```

### Dynamic Model Settings
```php
// In database: dynamic_models table
[
    'name' => 'products',
    'is_active' => true,
    'generate_api' => true,
    'list_rule' => 'true',      // RLS rule
    'create_rule' => 'true',
    'update_rule' => 'true',
    'delete_rule' => 'true',
]
```

---

## 🧪 Testing

### Manual Test
```bash
./test-core-api.sh
```

### cURL Test
```bash
# Test authentication
curl http://127.0.0.1:8001/api/v1/data/products
# Should return 401

# Test with key
curl -H "Authorization: Bearer sk_xxx" \
  http://127.0.0.1:8001/api/v1/data/products
# Should return 200
```

### Check Logs
```bash
tail -f storage/logs/laravel.log
```

---

## 📚 Documentation Files

- `CORE_API_ENGINE_MIGRATION.md` - Technical details
- `CORE_API_TESTING_GUIDE.md` - Testing instructions
- `CORE_API_ARCHITECTURE.md` - Architecture diagrams
- `TASK_COMPLETE_SUMMARY.md` - Executive summary
- `QUICK_REFERENCE.md` - This file

---

## 🎯 Features

✅ Iron Dome (API key security)  
✅ Schema Doctor (Dynamic validation)  
✅ Turbo Cache (Performance)  
✅ Live Wire (Real-time events)  
✅ Transaction Wrapper (Atomicity)  
✅ Type-Safe Casting (Data integrity)  
✅ Rate Limiting (Per-key limits)  
✅ Backward Compatible (Legacy endpoints)  

---

## 🆘 Troubleshooting

### "Model not found"
- Check `dynamic_models` table
- Ensure `is_active = true`
- Ensure `generate_api = true`

### "Access denied by security rules"
- Check RLS rules in `dynamic_models`
- Set to `'true'` for testing

### "Table does not exist"
- Table hasn't been created
- Check `table_name` in `dynamic_models`

### Rate limit headers missing
- Ensure `ApiRateLimiter` middleware is applied
- Check `routes/api.php`

### Validation errors
- Check `dynamic_fields` table
- Verify field types and requirements

---

## 🚀 Quick Commands

```bash
# Get API key
php artisan tinker
>>> App\Models\ApiKey::first()->key

# Create API key
php artisan tinker
>>> App\Models\ApiKey::create([
    'user_id' => 1,
    'name' => 'Test Key',
    'key' => App\Models\ApiKey::generateKey('secret'),
    'type' => 'secret',
    'scopes' => ['read', 'write', 'delete'],
    'is_active' => true,
]);

# Check rate limit
php artisan tinker
>>> App\Models\ApiKey::first()->rate_limit

# Update rate limit
php artisan tinker
>>> App\Models\ApiKey::first()->update(['rate_limit' => 100]);

# Clear cache
php artisan cache:clear

# View logs
tail -f storage/logs/laravel.log

# Run tests
./test-core-api.sh
```

---

**Need more help? Check the full documentation files! 📖**
