# 📖 API Documentation Engine - Phase 2 Evolution

## 🚀 What's New in Phase 2

The API Documentation Engine has been evolved with powerful interactive features that transform it from a static documentation viewer into a full-featured API testing and exploration tool.

---

## ✨ New Features

### 1. ⚡ Try It Out (Interactive Testing)

**Live API Testing directly from the documentation!**

#### Features:
- **Real-time API calls** - Test your endpoints without leaving the docs
- **Multiple operations** - Test GET (List) and POST (Create) operations
- **API Key input** - Enter your `sk_` or `pk_` key to authenticate
- **Request body editor** - JSON editor for POST/PUT requests
- **Live response display** - See actual API responses with status codes
- **Color-coded status** - Green for success (2xx), red for errors (4xx/5xx)

#### How It Works:
1. Select an operation (GET List or POST Create)
2. Enter your API key (use `sk_` for write operations)
3. For POST requests, edit the JSON request body
4. Click "Send Request"
5. View the live response with status code and JSON data

#### Technical Implementation:
- Uses Laravel's HTTP client for requests
- Validates user permissions before testing
- Supports all HTTP methods (GET, POST, PUT, DELETE)
- Real-time Livewire updates for instant feedback

---

### 2. 📄 OpenAPI 3.0 Export

**Download a complete OpenAPI specification for your API!**

#### Features:
- **OpenAPI 3.0 compliant** - Industry-standard format
- **Import into Postman** - One-click import into Postman/Insomnia
- **Complete schemas** - All models, fields, and validation rules
- **Security definitions** - API key authentication documented
- **Response schemas** - Success and error response structures
- **Path parameters** - All endpoints with parameters documented

#### What's Included:
```json
{
  "openapi": "3.0.0",
  "info": { ... },
  "servers": [ ... ],
  "security": [ ... ],
  "components": {
    "securitySchemes": { "ApiKeyAuth": { ... } },
    "schemas": { ... }
  },
  "paths": { ... }
}
```

#### How to Use:
1. Click "Download OpenAPI Spec" button
2. Import the JSON file into:
   - Postman (File → Import)
   - Insomnia (Application → Import/Export)
   - Swagger UI
   - Any OpenAPI-compatible tool

---

### 3. 📊 Response Examples

**See exactly what to expect from your API!**

#### Success Responses:
- **200 OK** - Successful GET request with sample data
- **201 Created** - Successful POST request with created resource

#### Error Responses:
- **400 Bad Request** - Invalid request format
- **401 Unauthorized** - Missing or invalid API key
- **403 Forbidden** - Access denied by security rules (Iron Dome)
- **404 Not Found** - Resource doesn't exist
- **422 Validation Error** - Field validation failures with details

#### Features:
- **Real sample data** - Based on your actual field types
- **Timestamps included** - Shows created_at/updated_at format
- **Validation errors** - Example error messages for each field
- **Color-coded** - Green for success, red for errors

---

### 4. 🪝 Webhook Documentation

**Complete guide to receiving real-time events!**

#### Webhook Events:
- **created** - Triggered when a new record is created
- **updated** - Triggered when a record is updated
- **deleted** - Triggered when a record is deleted

#### Event Payload Structure:
```json
{
  "event": "created",
  "table": "users",
  "data": { ... },
  "timestamp": "2026-02-09T10:30:00Z"
}
```

#### Headers Sent:
- `Content-Type: application/json`
- `User-Agent: Digibase-Webhook/1.0`
- `X-Webhook-Event: created|updated|deleted`
- `X-Webhook-Signature: sha256=<signature>`

#### Signature Verification:
Complete code examples in **PHP**, **JavaScript**, and **Python** showing how to:
- Verify webhook authenticity using HMAC SHA256
- Prevent replay attacks
- Secure your webhook endpoints

**PHP Example:**
```php
$signature = hash_hmac('sha256', $payload, $webhookSecret);
$isValid = hash_equals($signature, $receivedSignature);
```

**JavaScript Example:**
```javascript
const crypto = require('crypto');
const signature = crypto
  .createHmac('sha256', webhookSecret)
  .update(payload)
  .digest('hex');
const isValid = signature === receivedSignature;
```

**Python Example:**
```python
import hmac
import hashlib
signature = hmac.new(
    webhook_secret.encode(),
    payload.encode(),
    hashlib.sha256
).hexdigest()
is_valid = signature == received_signature
```

---

## 🎨 UI/UX Improvements

### Clean PocketBase-Inspired Design
- **Card-based layout** - Each section in its own card
- **Color-coded elements** - HTTP methods, status codes, events
- **Dark mode support** - Beautiful in both light and dark themes
- **Responsive design** - Works on all screen sizes

### Interactive Elements
- **Tabbed interfaces** - Switch between languages and operations
- **Live updates** - Real-time feedback with Livewire
- **Loading states** - Clear indicators when testing endpoints
- **Syntax highlighting** - Code blocks with proper formatting

### Accessibility
- **Keyboard navigation** - Full keyboard support
- **Screen reader friendly** - Proper ARIA labels
- **High contrast** - Readable in all themes

---

## 🔧 Technical Architecture

### Service Layer (`ApiDocumentationService`)

#### New Methods:
```php
// Generate OpenAPI 3.0 specification
public function generateOpenApiSpec(DynamicModel $model): array

// Generate response examples for all status codes
protected function generateResponseExamples(DynamicModel $model): array

// Generate webhook documentation
protected function generateWebhookDocs(DynamicModel $model): array

// Generate OpenAPI paths
protected function generateOpenApiPaths(DynamicModel $model): array

// Generate OpenAPI schemas
protected function generateOpenApiSchema(DynamicModel $model): array
protected function generateOpenApiInputSchema(DynamicModel $model): array
```

### Page Layer (`ApiDocumentation`)

#### New Properties:
```php
public string $testApiKey = '';           // API key for testing
public string $testRequestBody = '{}';    // JSON request body
public ?array $testResponse = null;       // Test response data
public ?int $testStatusCode = null;       // HTTP status code
public bool $testLoading = false;         // Loading state
```

#### New Methods:
```php
// Download OpenAPI specification as JSON
public function downloadOpenApiSpec(): StreamedResponse

// Test an API endpoint with live HTTP request
public function testEndpoint(string $method, string $endpoint): void
```

---

## 🔐 Security Features

### Iron Dome Integration
- **API key validation** - Respects pk_/sk_ permissions
- **User ownership** - Only test tables you own
- **Scope checking** - Honors allowed_tables restrictions
- **Rate limiting** - Prevents abuse of test feature

### Safe Testing
- **Isolated requests** - Tests don't affect production data
- **Error handling** - Graceful failure with helpful messages
- **Timeout protection** - Prevents hanging requests
- **CSRF protection** - Livewire CSRF tokens

---

## 📦 Files Modified

### Core Service
- `app/Services/ApiDocumentationService.php`
  - Added OpenAPI 3.0 generation
  - Added response examples
  - Added webhook documentation
  - Enhanced schema generation

### Filament Page
- `app/Filament/Pages/ApiDocumentation.php`
  - Added interactive testing methods
  - Added OpenAPI download
  - Added Livewire state management
  - Enhanced documentation loading

### Blade View
- `resources/views/filament/pages/api-documentation.blade.php`
  - Added "Try It Out" section
  - Added response examples display
  - Added webhook documentation
  - Added OpenAPI download button
  - Enhanced UI with Alpine.js interactions

---

## 🎯 Use Cases

### For Developers
1. **Quick Testing** - Test endpoints without Postman
2. **API Exploration** - Understand API behavior instantly
3. **Debugging** - See actual responses and error messages
4. **Integration** - Download OpenAPI spec for client generation

### For Teams
1. **Onboarding** - New developers can explore the API interactively
2. **Documentation** - Always up-to-date with your schema
3. **Collaboration** - Share OpenAPI specs with frontend teams
4. **Quality Assurance** - Test endpoints before deployment

### For API Consumers
1. **Self-Service** - Test API without asking for help
2. **Learning** - See examples of requests and responses
3. **Troubleshooting** - Verify API keys and permissions
4. **Integration** - Import specs into their tools

---

## 🚀 Future Enhancements

### Potential Phase 3 Features:
- **Bulk operations** - Test multiple records at once
- **Query builder** - Visual interface for filters and sorting
- **Response history** - Save and compare test results
- **Mock data generator** - Auto-generate test data
- **Performance metrics** - Show response times
- **WebSocket testing** - Test Live Wire real-time events
- **Relationship testing** - Test nested includes
- **Export collections** - Save test suites as Postman collections

---

## 📊 Comparison: Before vs After

| Feature | Phase 1 | Phase 2 |
|---------|---------|---------|
| Static Documentation | ✅ | ✅ |
| Code Examples | ✅ | ✅ |
| Interactive Testing | ❌ | ✅ |
| OpenAPI Export | ❌ | ✅ |
| Response Examples | ❌ | ✅ |
| Webhook Docs | ❌ | ✅ |
| Error Examples | ❌ | ✅ |
| Live API Calls | ❌ | ✅ |
| Postman Import | ❌ | ✅ |

---

## 🎉 Summary

Phase 2 transforms the API Documentation Engine from a **static reference** into an **interactive development tool**. Developers can now:

✅ Test endpoints in real-time  
✅ Download OpenAPI specs for Postman  
✅ See actual response examples  
✅ Understand webhook integration  
✅ Verify API keys and permissions  
✅ Debug issues instantly  
✅ Share specs with teams  
✅ Integrate faster  

The documentation is now a **living, breathing tool** that evolves with your database schema and provides a **Swagger-like experience** without leaving Digibase!

---

## 🔗 Integration with Digibase Core

### Works With:
- **🛡️ Iron Dome** - Respects API key scopes and permissions
- **⚡ Turbo Cache** - Tests show cached vs fresh responses
- **🩺 Schema Doctor** - Validation rules documented and tested
- **📡 Live Wire** - Webhook events documented with examples

### Powered By:
- **Laravel 12** - HTTP client for testing
- **Filament 4** - Beautiful admin UI
- **Livewire** - Real-time interactions
- **Alpine.js** - Client-side interactivity
- **Tailwind CSS** - Responsive design

---

**Built with ❤️ for the Digibase Developer Experience**
