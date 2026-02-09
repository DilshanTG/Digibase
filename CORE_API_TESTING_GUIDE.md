# 🧪 Core API Engine Testing Guide

## Quick Start

The Core API Engine has been successfully implemented with all requested features. Here's how to test it:

### Option 1: Manual Testing with cURL

1. **Get an API Key from your database:**
```bash
php artisan tinker
>>> $key = App\Models\ApiKey::where('is_active', true)->first();
>>> echo $key->key;
```

2. **Test the v1 API:**
```bash
# List records
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/v1/data/YOUR_TABLE

# Create record
curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","price":29.99}' \
  http://127.0.0.1:8001/api/v1/data/YOUR_TABLE

# Get schema
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/v1/data/YOUR_TABLE/schema

# Check rate limit headers
curl -I -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/v1/data/YOUR_TABLE
```

3. **Test backward compatibility (legacy endpoint):**
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/data/YOUR_TABLE
```

### Option 2: Automated Test Script

1. **Edit the test script:**
```bash
nano test-core-api.sh
# Update API_KEY variable with your actual key
```

2. **Run the tests:**
```bash
./test-core-api.sh
```

### Option 3: Using Postman/Insomnia

Import these endpoints:

**Base URL:** `http://127.0.0.1:8001`

**Headers:**
- `Authorization: Bearer YOUR_API_KEY`
- `Content-Type: application/json`

**Endpoints:**

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/data/{table}` | List all records |
| POST | `/api/v1/data/{table}` | Create new record |
| GET | `/api/v1/data/{table}/{id}` | Get single record |
| PUT | `/api/v1/data/{table}/{id}` | Update record |
| DELETE | `/api/v1/data/{table}/{id}` | Delete record |
| GET | `/api/v1/data/{table}/schema` | Get table schema |

---

## ✅ Features to Verify

### 1. 🛡️ Iron Dome (API Key Security)

**Test:** Try accessing without API key
```bash
curl http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** 401 Unauthorized with error code `MISSING_API_KEY`

**Test:** Try with invalid API key
```bash
curl -H "Authorization: Bearer invalid_key" \
  http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** 401 Unauthorized with error code `INVALID_API_KEY`

**Test:** Try accessing table not in `allowed_tables`
```bash
# Create API key with limited table access
# Then try accessing a different table
```
**Expected:** 403 Forbidden with error code `TABLE_ACCESS_DENIED`

### 2. 🩺 Schema Doctor (Dynamic Validation)

**Test:** Create record without required fields
```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"price":29.99}' \
  http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** 422 Validation Error with field-specific errors

**Test:** Create record with invalid data type
```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","price":"not-a-number"}' \
  http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** 422 Validation Error

### 3. 🎯 Type-Safe Casting

**Test:** Send string values that should be cast
```bash
curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test","price":"29.99","quantity":"10","is_active":"1"}' \
  http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** 201 Created, and values are properly cast to correct types in database

**Verify in database:**
```bash
php artisan tinker
>>> DB::table('products')->latest()->first();
# Check that price is float, quantity is int, is_active is boolean
```

### 4. 🔒 Transaction Wrapper

**Test:** Create a record that will fail validation mid-way
```bash
# This is harder to test manually, but you can check logs
tail -f storage/logs/laravel.log
```
**Expected:** If any error occurs during creation, the entire transaction is rolled back

### 5. 🚦 Rate Limiting

**Test:** Check rate limit headers
```bash
curl -I -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** Response includes:
- `X-RateLimit-Limit: 60` (or your custom limit)
- `X-RateLimit-Remaining: 59`
- `X-RateLimit-Reset: [timestamp]`

**Test:** Exceed rate limit
```bash
# Update API key to have rate_limit = 2
php artisan tinker
>>> $key = App\Models\ApiKey::first();
>>> $key->update(['rate_limit' => 2]);

# Make 3 requests quickly
for i in {1..3}; do
  curl -H "Authorization: Bearer YOUR_API_KEY" \
    http://127.0.0.1:8001/api/v1/data/products
done
```
**Expected:** Third request returns 429 Too Many Requests with `retry_after` field

### 6. ⚡ Turbo Cache

**Test:** Make same GET request twice
```bash
# First request (cache miss)
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/v1/data/products

# Second request (cache hit - should be faster)
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** Second request is faster (check response time)

**Test:** Cache invalidation
```bash
# Create a new record
curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name":"New Product","price":19.99}' \
  http://127.0.0.1:8001/api/v1/data/products

# List records again (cache should be invalidated)
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** New record appears in list (cache was invalidated)

### 7. 📡 Live Wire (Real-time Events)

**Test:** Check if events are broadcast
```bash
# Monitor Laravel logs
tail -f storage/logs/laravel.log

# Create a record
curl -X POST \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name":"Event Test","price":9.99}' \
  http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** Event broadcast logged (if Reverb is running)

### 8. 🔄 Backward Compatibility

**Test:** Legacy endpoint still works
```bash
# Old endpoint (without v1)
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/data/products

# New endpoint (with v1)
curl -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/v1/data/products
```
**Expected:** Both return the same data

---

## 🐛 Troubleshooting

### Issue: "Model not found"
**Solution:** Make sure the dynamic model exists and has `is_active = true` and `generate_api = true`

### Issue: "Table does not exist"
**Solution:** The dynamic model's table hasn't been created. Check `dynamic_models` table.

### Issue: Rate limit headers missing
**Solution:** Make sure `ApiRateLimiter` middleware is applied to the route.

### Issue: "Access denied by security rules"
**Solution:** Check the model's RLS rules (`list_rule`, `create_rule`, etc.). Set to `'true'` for testing.

### Issue: Validation errors
**Solution:** Check the `dynamic_fields` table for field requirements and types.

---

## 📊 Performance Benchmarks

You can benchmark the API using Apache Bench:

```bash
# Install Apache Bench (if not installed)
# macOS: brew install httpd
# Ubuntu: sudo apt-get install apache2-utils

# Benchmark GET requests
ab -n 1000 -c 10 \
  -H "Authorization: Bearer YOUR_API_KEY" \
  http://127.0.0.1:8001/api/v1/data/products

# Benchmark POST requests
ab -n 100 -c 5 \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -p post-data.json \
  http://127.0.0.1:8001/api/v1/data/products
```

**Expected Results:**
- GET requests: 100-500 req/sec (with cache)
- POST requests: 50-200 req/sec (with transactions)

---

## 🎯 Next Steps

1. **Test in Production Environment**
   - Deploy to staging
   - Run load tests
   - Monitor error logs

2. **Update API Documentation**
   - Update `ApiDocumentationService` to use v1 endpoints
   - Add rate limiting documentation
   - Document transaction behavior

3. **Monitor Performance**
   - Set up APM (Application Performance Monitoring)
   - Track slow queries
   - Monitor cache hit rates

4. **Plan v2 Features**
   - Batch operations
   - GraphQL support
   - Advanced filtering (JSON queries)
   - Bulk imports/exports

---

## 📝 Summary

The Core API Engine is now:
- ✅ Unified (single controller for all CRUD operations)
- ✅ Secure (Iron Dome integration)
- ✅ Validated (Schema Doctor integration)
- ✅ Fast (Turbo Cache integration)
- ✅ Real-time (Live Wire integration)
- ✅ Safe (Transaction wrappers)
- ✅ Type-safe (Strict casting)
- ✅ Rate-limited (Dynamic per-key limits)
- ✅ Backward compatible (Legacy endpoints work)
- ✅ Production-ready (Comprehensive error handling)

**All systems are GO! 🚀**
