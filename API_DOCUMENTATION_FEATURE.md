# 📖 Dynamic API Documentation Engine

## Overview
The Dynamic API Documentation Engine automatically generates comprehensive API documentation for all dynamically created tables in Digibase. Users can now see exactly how to interact with their custom tables via the REST API without guessing endpoints or JSON structures.

## ✅ What Was Implemented

### 1. **ApiDocumentationService** (`app/Services/ApiDocumentationService.php`)
A comprehensive service that generates:
- **Endpoint URLs** for all CRUD operations (GET, POST, PUT, DELETE)
- **Authentication documentation** (API key types: `pk_` and `sk_`)
- **Request/Response schemas** based on DynamicField types
- **Code examples** in three languages:
  - cURL
  - JavaScript (with digibase.js SDK and fetch API)
  - Python (with requests library)
- **JSON Schema** with validation rules and constraints

### 2. **ApiDocumentation Page** (`app/Filament/Pages/ApiDocumentation.php`)
A dedicated Filament page that:
- Lists all active dynamic models
- Allows users to select a table to view its documentation
- Displays comprehensive API documentation in a beautiful UI

### 3. **Documentation View** (`resources/views/filament/pages/api-documentation.blade.php`)
A rich Blade template featuring:
- **Model Overview** - Name, description, and table name
- **Authentication Section** - API key types and usage
- **Endpoints Section** - All available endpoints with parameters and request bodies
- **Code Examples** - Interactive tabs for different languages and operations
- **JSON Schema** - Complete schema with validation rules

### 4. **DataExplorer Integration**
Added an "API Docs" button to the DataExplorer page that:
- Opens the API documentation for the currently selected table
- Provides quick access to documentation while browsing data

## 🎯 Features

### Automatic Documentation Generation
- Analyzes `DynamicModel` and `DynamicField` relationships
- Generates accurate endpoint URLs based on table names
- Creates sample data based on field types
- Includes validation rules from Schema Doctor

### Multi-Language Code Examples
Each operation (List, Get, Create, Update, Delete) includes examples for:
- **cURL** - Command-line HTTP requests
- **JavaScript** - Both digibase.js SDK and native fetch API
- **Python** - Using the requests library

### Security Documentation
- Explains API key authentication (`x-api-key` header)
- Documents public keys (`pk_`) vs secret keys (`sk_`)
- Shows proper authentication examples

### Field Type Support
Automatically generates correct sample values for all field types:
- String, Text, RichText, Markdown
- Integer, Float, Decimal
- Boolean, Date, DateTime, Time
- JSON, UUID, Enum, Select
- Email, URL, Phone, Slug
- Password, Color, Encrypted
- File, Image, Point (GPS)

## 🚀 How to Use

### Access the Documentation
1. Navigate to **Developer → API Documentation** in the Filament admin panel
2. Select a table from the dropdown
3. View comprehensive documentation for that table's API

### From Data Explorer
1. Go to **Database → Data Explorer**
2. Select a table to view
3. Click the **"API Docs"** button in the header
4. Documentation opens in a new tab

### Try the Examples
1. Copy any code example from the documentation
2. Replace `sk_your_secret_key_here` with your actual API key
3. Run the code to interact with your API

## 📋 Documentation Sections

### 1. Model Overview
- Display name and description
- Physical table name
- Active status

### 2. Authentication
- API key types and their permissions
- Header format (`x-api-key`)
- Security best practices

### 3. API Endpoints
For each endpoint, you'll see:
- HTTP method (GET, POST, PUT, DELETE)
- Full URL path
- Description of what it does
- Query parameters (for GET requests)
- Request body schema (for POST/PUT)
- Field validation rules

### 4. Code Examples
Interactive examples with tabs for:
- **Languages**: cURL, JavaScript, Python
- **Operations**: List All, Get One, Create, Update, Delete

### 5. JSON Schema
Complete JSON schema showing:
- All field properties
- Data types
- Required fields
- Validation constraints

## 🔧 Technical Details

### Service Architecture
```php
ApiDocumentationService
├── generateDocumentation()      // Main entry point
├── generateEndpoints()          // CRUD endpoint definitions
├── generateAuthenticationDocs() // API key documentation
├── generateExamples()           // Code samples
├── generateSchema()             // JSON schema
└── Helper methods for type mapping and validation
```

### Integration Points
- **DynamicModel**: Source of table metadata
- **DynamicField**: Field definitions and validation rules
- **Schema Doctor**: Validation rules are included in docs
- **Iron Dome**: API key authentication is documented

### Sample Data Generation
The service intelligently generates sample values based on field types:
```php
'string' => "sample_{field_name}"
'integer' => 42
'email' => "sample_email"
'date' => "2026-02-09"
'boolean' => true
// ... and more
```

## 🎨 UI Features

### Beautiful Design
- Clean, modern interface using Filament components
- Dark mode support
- Color-coded HTTP methods (GET=blue, POST=green, PUT=yellow, DELETE=red)
- Syntax-highlighted code blocks

### Interactive Elements
- Tabbed interface for switching between languages
- Operation selector for different CRUD operations
- Collapsible sections for better organization
- Copy-friendly code blocks

### Responsive Layout
- Works on desktop and mobile devices
- Proper spacing and typography
- Accessible color contrast

## 🔐 Security Considerations

### Permission Checking
- Only shows tables the user has permission to see
- Respects `user_id` ownership
- Honors `is_active` and `generate_api` flags

### Sensitive Data
- Doesn't expose internal implementation details
- Shows only public API surface
- Includes security best practices in examples

## 🧪 Testing the Feature

### Manual Testing
1. Create a dynamic table with various field types
2. Navigate to API Documentation
3. Select your table
4. Verify all sections display correctly
5. Copy and test a code example

### Validation
- Check that field types map correctly to JSON types
- Verify validation rules are displayed
- Ensure sample data matches field types
- Test with tables that have relationships

## 📝 Future Enhancements

Potential improvements:
- **Try It Out** - Interactive API testing directly from docs
- **Export Options** - Download as OpenAPI/Swagger JSON
- **Webhook Documentation** - Document webhook events
- **Rate Limiting Info** - Show rate limits per endpoint
- **Response Examples** - Show sample API responses
- **Error Codes** - Document all possible error responses

## 🎉 Summary

The Dynamic API Documentation Engine provides:
✅ Automatic documentation generation
✅ Multi-language code examples
✅ Beautiful, interactive UI
✅ Security documentation
✅ Schema validation rules
✅ Quick access from Data Explorer
✅ Support for all field types
✅ Dark mode support

Users can now confidently build applications using Digibase's API without guessing how to structure their requests!
