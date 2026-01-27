# Digibase - Build Guide
## World's First Open Source Laravel BaaS (Backend as a Service)

**"The Supabase Alternative for Laravel Developers"**

---

## 🎯 Project Overview

**Name:** Digibase  
**Tagline:** "Supabase for Laravel - Open Source BaaS"  
**Goal:** GUI-first Laravel backend with auto APIs  
**Target:** Sri Lankan & global Laravel developers  
**Unique Selling Point:** First FREE Laravel BaaS with full GUI!

**Tech Stack:**
- Backend: Laravel 11
- Frontend: React 18 + Vite
- Admin: FilamentPHP v3
- API: Laravel Orion
- Real-time: Laravel Reverb
- Auth: Laravel Sanctum

---

## 📋 Build Timeline

**Total Duration:** 20 days  
**Estimated Hours:** 150-200 hours  
**Approach:** Use Manus AI (or similar coding assistant) with prompts below

---

# 🚀 Phase 1: Foundation (Day 1-2)

## Prompt 1: Initialize Base Project

```
Create a new Laravel 11 project called "digibase" with the following structure:

Project Requirements:
- Laravel 11 (latest stable version)
- Separate backend and frontend folders
- Backend: Laravel API
- Frontend: React + Vite admin panel

Folder Structure:
digibase/
├── backend/     (Laravel API)
└── frontend/    (React admin panel)

Backend - Install these packages:
- laravel/sanctum (API authentication)
- tailflow/laravel-orion (auto REST API generation)
- filament/filament:"^3.0" (admin panel base)
- dedoc/scramble (automatic API documentation)
- spatie/laravel-permission (roles and permissions)

Backend - Configuration:
- Enable API routes
- Configure CORS to allow frontend origin
- Setup Sanctum for SPA authentication
- Configure database connection (MySQL)

Frontend Requirements:
- React 18
- Vite (build tool)
- TailwindCSS (styling)
- React Router v6 (routing)
- Axios (HTTP client)
- React Query / TanStack Query (data fetching)
- Heroicons (icons)

Frontend - Setup:
- Configure Vite proxy to backend
- Setup Axios base configuration
- Create basic folder structure:
  src/
  ├── components/
  ├── pages/
  ├── hooks/
  ├── lib/
  ├── styles/
  └── App.jsx

Create proper .env files for both backend and frontend with:
- Database credentials
- API URLs
- CORS origins
- APP_KEY

Make sure both can run simultaneously on different ports:
- Backend: http://localhost:8000
- Frontend: http://localhost:5173
```

---

## Prompt 2: Setup Authentication System

```
Setup complete authentication system for Digibase:

Backend (Laravel) - API Endpoints:
POST /api/register
- Input: name, email, password, password_confirmation
- Validation: email unique, password min 8 chars
- Returns: user object + access token

POST /api/login
- Input: email, password
- Returns: user object + access token
- Error: 401 if credentials invalid

POST /api/logout
- Requires: Bearer token
- Revokes current token
- Returns: success message

GET /api/user
- Requires: Bearer token
- Returns: authenticated user object

POST /api/forgot-password
- Input: email
- Sends password reset link
- Returns: success message

POST /api/reset-password
- Input: token, email, password, password_confirmation
- Resets password
- Returns: success message

Backend - User Model:
- Add roles field (enum: admin, developer, user)
- Add email_verified_at
- Add remember_token
- Implement Sanctum HasApiTokens trait

Backend - Middleware:
- API authentication using Sanctum
- Role-based access control
- Rate limiting on auth endpoints

Frontend (React) - Pages:
1. Login Page:
   - Email and password fields
   - "Remember me" checkbox
   - "Forgot password?" link
   - Beautiful gradient background
   - Form validation
   - Loading state
   - Error display

2. Register Page:
   - Name, email, password, confirm password
   - Password strength indicator
   - Terms acceptance checkbox
   - Beautiful design matching login

3. Forgot Password Page:
   - Email input
   - Send reset link button
   - Success message display

Frontend - Auth Context:
- Create AuthContext with provider
- Store token in localStorage
- Store user data in context state
- Login function
- Logout function
- Check if authenticated
- Get current user

Frontend - Protected Routes:
- ProtectedRoute component
- Redirect to login if not authenticated
- Automatic token refresh logic

Frontend - API Integration:
- Axios interceptor to add Bearer token
- Axios interceptor to handle 401 (auto logout)
- Axios interceptor to handle network errors

Make the authentication system production-ready with:
- CSRF protection
- XSS prevention
- SQL injection protection
- Rate limiting
- Proper error messages
- Loading states
- Success feedback
```

---

# 🔧 Phase 2: Core BaaS Features (Day 3-5)

## Prompt 3: Visual Model Creator (CORE FEATURE!)

```
Build the CORE feature of Digibase - Visual Model Creator!

This is the most important feature - users create Laravel models visually without touching code!

Backend - Create Model Generator Service:

Class: App\Services\ModelGeneratorService

Methods:
1. generateModel($modelData)
   - Input: model name, fields array, options
   - Creates Laravel Model file
   - Creates Migration file
   - Creates Filament Resource
   - Registers Orion API route
   - Returns: success/error with file paths

2. generateMigration($modelName, $fields)
   - Creates migration file in database/migrations
   - Handles all field types: string, text, integer, decimal, boolean, date, datetime, json, etc.
   - Adds indexes
   - Adds foreign keys
   - Adds timestamps
   - Adds soft deletes if requested

3. generateFilamentResource($modelName, $fields)
   - Creates Filament resource in app/Filament/Resources
   - Auto-generates form fields
   - Auto-generates table columns
   - Adds proper validation

4. registerOrionRoute($modelName)
   - Appends route to routes/api.php
   - Format: Orion::resource('products', ProductController::class);

Backend - API Endpoint:
POST /api/models/generate

Request Body Format:
{
  "name": "Product",
  "table_name": "products", // optional, auto-generated from name
  "fields": [
    {
      "name": "name",
      "type": "string",
      "length": 255,
      "nullable": false,
      "default": null,
      "unique": false,
      "index": false
    },
    {
      "name": "price",
      "type": "decimal",
      "precision": [10, 2],
      "nullable": false
    },
    {
      "name": "description",
      "type": "text",
      "nullable": true
    },
    {
      "name": "category_id",
      "type": "foreignId",
      "references": "categories",
      "onDelete": "cascade"
    }
  ],
  "timestamps": true,
  "softDeletes": false,
  "fillable": ["name", "price", "description", "category_id"]
}

Response:
{
  "success": true,
  "message": "Model created successfully!",
  "files_created": [
    "app/Models/Product.php",
    "database/migrations/2024_01_01_000000_create_products_table.php",
    "app/Filament/Resources/ProductResource.php",
    "app/Http/Controllers/Api/ProductController.php"
  ],
  "api_endpoint": "/api/products"
}

Frontend - Model Creator Page:

Create beautiful form with sections:

Section 1: Basic Info
- Model name input (required)
- Table name input (auto-filled, editable)
- Description textarea (optional)

Section 2: Fields Builder
- "Add Field" button
- For each field:
  - Field name input
  - Field type dropdown:
    * string
    * text
    * integer
    * decimal
    * boolean
    * date
    * datetime
    * json
    * foreignId (relationship)
  - Length/precision input (conditional based on type)
  - Nullable checkbox
  - Unique checkbox
  - Index checkbox
  - Default value input
  - "Remove" button
  
- Drag-drop to reorder fields

Section 3: Options
- Timestamps checkbox (checked by default)
- Soft deletes checkbox
- Generate API checkbox (checked by default)
- Generate Admin Panel checkbox (checked by default)

Section 4: Code Preview
- Live preview of generated:
  - Model code
  - Migration code
  - API routes
- Syntax highlighting
- Toggle visibility

Section 5: Actions
- "Generate Model" button (primary, large)
- "Save as Template" button
- "Cancel" button

UI/UX Requirements:
- Beautiful gradient background
- Smooth animations
- Real-time validation
- Field type icons
- Helpful tooltips
- Loading state during generation
- Success celebration animation
- Error display with suggestions

After successful generation:
- Show success modal
- Display created files
- Show API endpoint URL with "Copy" button
- "Create Another Model" button
- "Go to Database" button
- "Test API" button

Make this the BEST model creator UI in the world!
Better than Supabase, better than Firebase!
```

---

## Prompt 4: Database Schema Viewer

```
Build visual database schema viewer and explorer for Digibase:

Backend - Database Inspector Service:

Create: App\Services\DatabaseInspectorService

Methods:
1. getAllTables()
   - Returns array of all table names
   - Includes row counts
   - Includes table size

2. getTableStructure($tableName)
   - Returns columns with types, lengths, defaults
   - Returns indexes
   - Returns foreign keys
   - Returns relationships

3. getTableRelationships()
   - Maps all foreign key relationships
   - Returns graph-ready format

Backend - API Endpoints:

GET /api/database/tables
- Returns all tables with metadata

GET /api/database/tables/{tableName}
- Returns detailed table structure

GET /api/database/relationships
- Returns all relationships for visualization

Response Format:
{
  "tables": [
    {
      "name": "products",
      "row_count": 150,
      "size": "2.5 MB",
      "columns": [
        {
          "name": "id",
          "type": "bigint",
          "nullable": false,
          "key": "PRI",
          "default": null,
          "extra": "auto_increment"
        },
        ...
      ],
      "indexes": [...],
      "foreign_keys": [...]
    }
  ],
  "relationships": [
    {
      "from": "products",
      "to": "categories",
      "type": "belongsTo"
    }
  ]
}

Frontend - Database Explorer Page:

Layout:
├── Sidebar (Left 25%)
│   ├── Search tables
│   ├── Table list with icons
│   └── Stats summary
│
└── Main Content (Right 75%)
    ├── Table details view
    └── Visual relationship diagram

Features:

1. Tables Sidebar:
   - Search/filter tables
   - Each table shows:
     * Icon (based on name/type)
     * Table name
     * Row count badge
     * Click to select
   - Grouped by type (system, user, etc.)
   - Expandable/collapsible groups

2. Table Details View (when table selected):
   - Table name and description
   - Stats: row count, size, created date
   - Tabs:
     Tab 1: Structure
       - List all columns
       - Show type, nullable, default
       - Show indexes with badges
       - Show constraints
     
     Tab 2: Data Preview
       - First 10 rows
       - Formatted nicely
       - "View All" link to Filament
     
     Tab 3: Relationships
       - Shows foreign keys
       - Shows reverse relationships
       - Visual arrows
     
     Tab 4: Indexes
       - Lists all indexes
       - Shows columns in index
       - Performance hints

3. Visual Schema Diagram:
   - Use React Flow library
   - Show all tables as nodes
   - Show relationships as edges
   - Zoom in/out
   - Pan
   - Click table to highlight
   - Export as image button

4. Actions per table:
   - "View Data" (opens Filament)
   - "Edit Structure" (coming soon)
   - "Delete Table" (with confirmation)
   - "Export SQL" button

Design:
- Clean, professional
- Database icon theme
- Color-coded by table type
- Smooth animations
- Responsive
- Dark mode support

Make it look like Supabase Table Editor but BETTER!
```

---

## Prompt 5: Auto API Documentation

```
Create automatic, beautiful API documentation page for Digibase:

Backend - Use Scramble package for OpenAPI generation

Setup Scramble:
- Install and configure
- Generate docs from routes
- Customize for Orion routes

Additional Endpoint:
GET /api/docs/endpoints
- Returns custom formatted endpoint list
- Includes examples
- Includes model relationships
- Returns available filters, sorts

Response Format:
{
  "models": [
    {
      "name": "Product",
      "endpoints": [
        {
          "method": "GET",
          "path": "/api/products",
          "description": "List all products",
          "query_params": ["filter", "sort", "include"],
          "example_request": "curl example...",
          "example_response": "{...}"
        },
        ...
      ]
    }
  ]
}

Frontend - API Documentation Page:

Layout:
├── Sidebar (Left)
│   ├── Search
│   ├── Models list
│   └── Quick links
│
└── Main Content (Right)
    ├── Selected endpoint details
    └── Interactive tester

Features:

1. Sidebar Navigation:
   - Search endpoints
   - Group by model
   - Click to jump to section
   - Color-coded by HTTP method
   - Sticky during scroll

2. Endpoint Documentation:
   For each endpoint show:
   
   - Method badge (GET, POST, etc.)
   - Full URL
   - Description
   - Authentication required badge
   
   - Request Section:
     * Headers needed
     * Query parameters table
     * Body parameters table (for POST/PATCH)
     * Parameter types and validation
   
   - Response Section:
     * Success response example (formatted JSON)
     * Error responses examples
     * Status codes table
   
   - Code Examples:
     * Tabs for: cURL, JavaScript, PHP, Python
     * Syntax highlighted
     * Copy button per example

3. Interactive API Tester:
   - "Try it out" button
   - Input fields for parameters
   - Headers editor
   - Body editor (JSON)
   - "Send Request" button
   - Response viewer:
     * Status code
     * Response time
     * Headers
     * Body (formatted)
   - Copy response button

4. Quick Start Guide:
   - Authentication setup
   - First API call example
   - Common patterns
   - Error handling

5. Advanced Features:
   - Filter documentation (Orion filters)
   - Relationship loading (includes)
   - Pagination
   - Sorting
   - Search

Design Requirements:
- Use Prism.js for syntax highlighting
- Beautiful code blocks
- Responsive layout
- Dark/light mode toggle
- Smooth scroll to sections
- Anchor links
- Copy buttons everywhere
- Loading states
- Professional appearance

Reference designs:
- Stripe API docs
- Supabase API docs
- Postman documentation

Make it the BEST API documentation developers have ever seen!
```

---

# 🎨 Phase 3: Admin Dashboard (Day 6-7)

## Prompt 6: Beautiful Dashboard

```
Create stunning, modern admin dashboard for Digibase:

Dashboard Components:

1. Stats Cards (Top Row):
   Card 1: Total Models
   - Count of models created
   - Trend indicator (up/down)
   - Icon: database
   - Color: blue gradient
   
   Card 2: API Endpoints
   - Count of active endpoints
   - Requests today count
   - Icon: code
   - Color: purple gradient
   
   Card 3: Database Size
   - Total size in MB/GB
   - Row count
   - Icon: hard drive
   - Color: green gradient
   
   Card 4: Active Projects
   - Number of projects
   - Icon: folder
   - Color: orange gradient

2. Recent Activity Feed:
   - Timeline style
   - Shows:
     * Models created
     * API calls made
     * Errors occurred
     * Database changes
   - Each item shows:
     * Icon
     * Action description
     * Timestamp
     * User who did it
   - Real-time updates

3. Quick Actions Panel:
   - Large buttons for:
     * Create New Model (primary, prominent)
     * View API Documentation
     * Database Explorer
     * Settings
   - Icons with text
   - Hover effects
   - Click animations

4. System Status:
   - Database connection status (green/red)
   - API status (green/red)
   - Queue worker status
   - Last backup time
   - Storage usage bar

5. Getting Started Guide (for new users):
   - Collapsible panel
   - Steps:
     1. Create your first model
     2. Test the API
     3. Build your frontend
     4. Deploy
   - Each step clickable
   - Progress indicator
   - Can be dismissed

6. Resources Section:
   - Links to:
     * Documentation
     * Video tutorials
     * Community Discord
     * GitHub
     * Support

7. Recent Models Table:
   - Shows last 5 models created
   - Columns: Name, Created, Endpoints, Actions
   - Quick actions per row

Design Specifications:

Layout:
- Grid-based (CSS Grid)
- Responsive (mobile, tablet, desktop)
- Proper spacing and padding

Visual Style:
- Modern glassmorphism design
- Subtle shadows and blur effects
- Gradient accents
- Smooth rounded corners
- Professional color palette:
  * Primary: #3B82F6 (blue)
  * Secondary: #8B5CF6 (purple)
  * Success: #10B981 (green)
  * Warning: #F59E0B (orange)
  * Danger: #EF4444 (red)

Animations:
- Fade in on load
- Number count-up animations for stats
- Smooth transitions
- Hover effects (scale, glow)
- Loading skeletons

Icons:
- Use Heroicons
- Consistent size and style
- Color-coded by action type

Dark Mode:
- Full dark mode support
- Toggle in header
- Saves preference
- Smooth transition

Interactions:
- Tooltips on hover
- Click feedback
- Loading states
- Success/error toasts

Make it look MORE impressive than:
- Supabase dashboard
- Firebase console
- Vercel dashboard
- Any competitor!

This dashboard should make developers say "WOW!" 🤩
```

---

## Prompt 7: Setup Wizard

```
Build first-time setup wizard for new Digibase installations:

Multi-Step Wizard (5 Steps Total):

Step 1: Welcome
- Large Digibase logo
- Animated entrance
- Welcome message: "Welcome to Digibase! Let's get you set up in just a few minutes."
- Features overview (3-4 bullet points with icons):
  * Visual model creator
  * Auto API generation
  * Beautiful admin panel
  * Free and open source
- "Let's Get Started" button (large, primary)
- "Skip Setup" link (small, bottom)
- Illustration/animation

Step 2: Database Configuration
- Title: "Connect Your Database"
- Form fields:
  * Database Host (default: localhost)
  * Database Port (default: 3306)
  * Database Name
  * Username
  * Password (password field)
- "Test Connection" button (secondary)
  * Shows loading spinner
  * Shows success checkmark or error
  * Live validation feedback
- Connection status indicator
- Help text: "Make sure your database exists and credentials are correct"
- "Previous" and "Next" buttons
- Can't proceed until connection successful

Step 3: Admin Account Creation
- Title: "Create Your Admin Account"
- Subtitle: "This will be your main account to manage Digibase"
- Form fields:
  * Full Name (required)
  * Email Address (required, validated)
  * Password (required, min 8 chars)
  * Confirm Password (required, must match)
- Password strength indicator:
  * Weak (red)
  * Medium (yellow)
  * Strong (green)
- Requirements checklist:
  * At least 8 characters
  * Contains uppercase
  * Contains lowercase
  * Contains number
- "Previous" and "Next" buttons

Step 4: Sample Data (Optional)
- Title: "Install Example Models?"
- Subtitle: "We can create some example models to help you get started"
- Checkbox: "Install example models"
- Preview card showing what will be created:
  * Product model (with name, price, description)
  * Category model (with name)
  * Sample data (10 products, 5 categories)
- Visual preview/mockup
- Benefits listed:
  * See how models work
  * Test the API immediately
  * Example to learn from
- "You can delete these anytime" note
- "Previous" and "Next" buttons

Step 5: Complete!
- Title: "You're All Set! 🎉"
- Success animation (confetti, checkmark)
- Summary of what was created:
  * Database connected ✓
  * Admin account created ✓
  * Example models installed ✓ (if selected)
- Next steps panel:
  1. Create your first model
  2. Explore the API documentation
  3. Join our community
- Large "Go to Dashboard" button
- "Quick Tips" sidebar:
  * Tip 1: Start by creating a model
  * Tip 2: Check API docs
  * Tip 3: Join Discord for help

Wizard Features:

Progress Indicator:
- Shows steps 1-5
- Current step highlighted
- Completed steps have checkmark
- Future steps grayed out
- Sticky at top

Navigation:
- Can go back (except on final step)
- Can't skip ahead
- Validation before proceeding
- Keyboard navigation (Enter to continue)

Visual Design:
- Clean, spacious layout
- Large, readable text
- Plenty of white space
- Consistent button styles
- Smooth transitions between steps
- Loading states
- Error messages inline
- Success feedback

Mobile Responsive:
- Works on all screen sizes
- Stacked layout on mobile
- Touch-friendly buttons

Persistence:
- Saves progress in database
- Can resume if user leaves
- Shows "Resume Setup" on login if incomplete

Error Handling:
- Clear error messages
- Helpful suggestions
- Retry options
- Support link if stuck

After Completion:
- Wizard marked as completed in database
- Never shows again (unless reset)
- User redirected to dashboard
- Welcome toast message

Make the wizard feel:
- Friendly and welcoming
- Not overwhelming
- Quick and easy
- Professional
- Confidence-inspiring

Test the wizard with:
- First-time users
- Different screen sizes
- Various database configurations
- Network errors
- Invalid inputs

The wizard should make setup EFFORTLESS! 🚀
```

---

# 💻 Phase 4: Developer Experience (Day 8-9)

## Prompt 8: Code Generator UI

```
Create comprehensive code snippet generator for developers:

Backend - Code Template Service:

Create: App\Services\CodeGeneratorService

Methods:
1. generateReactComponent($modelName, $operation, $style)
2. generateVueComponent($modelName, $operation, $style)
3. generateNextJsComponent($modelName, $operation)
4. generateNuxtComponent($modelName, $operation)

Supported Operations:
- list (show all records)
- create (create form)
- update (edit form)
- delete (delete function)
- search (search functionality)

Supported Styles:
- tailwind
- bootstrap
- material-ui
- shadcn-ui

API Endpoint:
POST /api/code/generate

Request:
{
  "model": "Product",
  "framework": "react", // react, vue, nextjs, nuxt
  "operation": "list", // list, create, update, delete, search
  "style": "tailwind",
  "typescript": true,
  "includes": ["validation", "error-handling", "loading-states"]
}

Response:
{
  "framework": "react",
  "files": [
    {
      "name": "ProductList.jsx",
      "code": "import React from 'react'...",
      "description": "Component to display product list"
    },
    {
      "name": "useProducts.js",
      "code": "import { useQuery } from '@tanstack/react-query'...",
      "description": "Custom hook for product data fetching"
    }
  ]
}

Frontend - Code Generator Page:

Layout:
├── Configuration Panel (Left 30%)
│   ├── Model selector
│   ├── Framework selector
│   ├── Operation selector
│   ├── Options
│   └── Generate button
│
└── Code Output Panel (Right 70%)
    ├── Generated files tabs
    ├── Code viewer
    └── Actions

Configuration Panel:

1. Model Selection:
   - Dropdown of all available models
   - Shows model fields preview
   - "Select Model" placeholder

2. Framework Selection:
   - Radio buttons with logos:
     * React
     * Vue 3
     * Next.js
     * Nuxt 3
     * Angular (future)
   - Tooltip explaining each

3. Operation Selection:
   - Checkbox list:
     * ☐ List/Index page
     * ☐ Create form
     * ☐ Edit form
     * ☐ Delete function
     * ☐ Search functionality
     * ☐ Detail view
   - Can select multiple
   - "Select All" option

4. Styling Framework:
   - Dropdown:
     * Tailwind CSS
     * Bootstrap 5
     * Material UI
     * Ant Design
     * Chakra UI
     * shadcn/ui
   - Preview of style

5. Advanced Options:
   - TypeScript toggle
   - Include validation checkbox
   - Include error handling checkbox
   - Include loading states checkbox
   - Include pagination checkbox
   - API base URL input

6. Generate Button:
   - Large, primary button
   - "Generate Code" text
   - Loading spinner when processing
   - Disabled until valid selections

Code Output Panel:

1. Tabs for each generated file:
   - Tab per file with icon
   - Active tab highlighted
   - Close button per tab

2. Code Viewer:
   - Syntax highlighted (Prism.js)
   - Line numbers
   - Collapsible code blocks
   - Search within code
   - Copy button (floating top-right)
   - Download button
   - "View in new window" button

3. File Information:
   - File name prominently displayed
   - File size
   - Description of file purpose
   - Dependencies list

4. Actions Bar:
   - "Copy All" button
   - "Download as ZIP" button
   - "Save as Template" button
   - "Share Link" button

Code Templates (Examples):

React Component Example:
```jsx
import React, { useState, useEffect } from 'react';
import { useProducts } from '../hooks/useProducts';

export default function ProductList() {
  const { data: products, isLoading, error } = useProducts();
  
  if (isLoading) return <div>Loading...</div>;
  if (error) return <div>Error: {error.message}</div>;
  
  return (
    <div className="grid grid-cols-3 gap-4">
      {products.map(product => (
        <div key={product.id} className="border rounded p-4">
          <h3 className="font-bold">{product.name}</h3>
          <p className="text-gray-600">{product.description}</p>
          <p className="text-lg font-bold">Rs. {product.price}</p>
        </div>
      ))}
    </div>
  );
}
```

Custom Hook Example:
```javascript
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../lib/api';

export function useProducts() {
  return useQuery({
    queryKey: ['products'],
    queryFn: async () => {
      const { data } = await api.get('/products');
      return data.data;
    }
  });
}

export function useCreateProduct() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: async (product) => {
      const { data } = await api.post('/products', product);
      return data.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries(['products']);
    }
  });
}
```

Features:

1. Live Preview (Optional):
   - Show component preview
   - Interactive if possible
   - Iframe sandbox

2. Customization:
   - Edit generated code inline
   - Re-generate with different options
   - Save custom templates

3. History:
   - Save generated code snippets
   - Quick access to previous generations
   - Export history

4. Template Library:
   - Pre-built templates
   - Community templates
   - Import/export templates

Design:
- Split screen layout
- Professional code editor feel
- Dark theme for code blocks
- Syntax highlighting
- Smooth animations
- Copy feedback (toast)

Make developers LOVE this feature!
It should save them HOURS of boilerplate coding! ⚡
```

---

## Prompt 9: API Testing Interface

```
Build comprehensive API testing interface (like Postman, but integrated):

Backend - API History Service:

Store API test history in database:
- Endpoint called
- Method
- Headers
- Body
- Response
- Status code
- Response time
- Timestamp
- User

API Endpoints:
GET /api/testing/history
POST /api/testing/save-request
DELETE /api/testing/history/{id}

Frontend - API Tester Page:

Layout:
├── Sidebar (Left 20%)
│   ├── Saved requests
│   ├── History
│   └── Collections
│
├── Request Builder (Center 50%)
│   ├── Method + URL
│   ├── Tabs: Params, Headers, Body
│   └── Send button
│
└── Response Viewer (Right 30%)
    ├── Status + Time
    ├── Headers
    └── Body

Request Builder:

1. Top Bar:
   - Method dropdown (GET, POST, PATCH, PUT, DELETE)
   - URL input (auto-complete from available endpoints)
   - "Send" button (large, primary)
   - "Save" button
   - Loading indicator

2. URL Builder:
   - Base URL pre-filled
   - Path parameters highlighted
   - Auto-complete suggestions
   - Recent URLs dropdown

3. Tabs:
   
   Tab 1: Query Params
   - Key-Value pairs
   - "Add Parameter" button
   - Bulk edit (text area)
   - Common params (filter, sort, include)
   - Enable/disable per param
   
   Tab 2: Headers
   - Key-Value pairs
   - "Add Header" button
   - Common headers dropdown:
     * Authorization (auto-filled)
     * Content-Type
     * Accept
   - Enable/disable per header
   
   Tab 3: Body (for POST/PATCH/PUT)
   - Raw JSON editor
   - Syntax highlighting
   - Validation
   - Format button (prettify)
   - Templates dropdown
   
   Tab 4: Auth
   - Auth type selector:
     * Bearer Token (auto from login)
     * API Key
     * Basic Auth
   - Token input
   - "Use current user token" checkbox

Response Viewer:

1. Status Bar:
   - HTTP status code with color:
     * 2xx: Green
     * 4xx: Orange
     * 5xx: Red
   - Response time
   - Response size
   - "Copy Response" button

2. Response Tabs:
   
   Tab 1: Body
   - Formatted JSON
   - Syntax highlighted
   - Collapsible sections
   - Search in response
   - Copy button
   
   Tab 2: Headers
   - Table of response headers
   - Key-value format
   - Copy button per header
   
   Tab 3: Cookies
   - Shows cookies set
   - Expiry time
   
   Tab 4: Raw
   - Raw response text
   - Useful for debugging

Sidebar:

1. Collections:
   - Group related requests
   - Create new collection
   - Drag requests to collections
   - Share collections

2. History:
   - Recent requests list
   - Shows: Method, URL, Status, Time
   - Click to load
   - Clear history button
   - Search history

3. Saved Requests:
   - Named requests
   - Click to load
   - Edit name
   - Delete button
   - Organize in folders

Features:

1. Examples Library:
   - Pre-filled requests for each model
   - CRUD operation examples
   - Filter examples
   - Include examples
   - Search examples

2. Environment Variables:
   - Define variables (BASE_URL, TOKEN, etc.)
   - Use {{variable}} in requests
   - Switch environments (dev, staging, prod)

3. Code Generation:
   - "Generate Code" button
   - Shows request as:
     * cURL
     * JavaScript/Axios
     * PHP/Guzzle
     * Python/Requests
   - Copy button per language

4. Request Chaining:
   - Save response values
   - Use in subsequent requests
   - Extract with JSONPath

5. Testing/Assertions:
   - Add tests to requests
   - Assert status code
   - Assert response contains
   - Show test results

6. Bulk Operations:
   - Run multiple requests
   - See results summary
   - Export results

Design:
- Clean, developer-friendly
- Dark theme option
- Resizable panels
- Keyboard shortcuts
- Fast and responsive
- Professional appearance

Keyboard Shortcuts:
- Cmd/Ctrl + Enter: Send request
- Cmd/Ctrl + S: Save request
- Cmd/Ctrl + K: Focus URL
- Cmd/Ctrl + /: Search history

Make it feel like:
- Postman (familiar)
- Thunder Client (fast)
- Insomnia (beautiful)

But INTEGRATED with Digibase! 🚀
```

---

# 🚀 Phase 5: Advanced Features (Day 10-12)

## Prompt 10: Real-time Features with Laravel Reverb

```
Add real-time capabilities using Laravel Reverb:

Backend Setup:

1. Install Laravel Reverb:
   - composer require laravel/reverb
   - php artisan reverb:install
   - Configure in .env

2. Create Events:

Event: ModelCreated
- Broadcasts when model created via API
- Contains: model name, data, user

Event: ModelUpdated
- Broadcasts when model updated
- Contains: model name, data, changes

Event: ModelDeleted
- Broadcasts when model deleted
- Contains: model name, id

Event: ApiRequestMade
- Broadcasts on each API call
- Contains: endpoint, method, status, time, user

Event: DatabaseChanged
- Broadcasts on migrations
- Contains: action, table name

3. Broadcasting Channels:

Private Channels:
- digibase.user.{userId} (user-specific)
- digibase.project.{projectId} (project-specific)

Public Channels:
- digibase.activity (global activity)
- digibase.notifications (global notifications)

4. Broadcast from API:
   - Intercept Orion API calls
   - Broadcast events automatically
   - No manual code in models

Frontend - Real-time Integration:

1. Setup Laravel Echo:
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});
```

2. Create Real-time Hook:
```javascript
// hooks/useRealtime.js
export function useRealtime() {
    useEffect(() => {
        // Listen for model updates
        window.Echo.channel('digibase.activity')
            .listen('ModelCreated', (e) => {
                // Show notification
                toast.success(`New ${e.modelName} created!`);
                // Invalidate queries to refetch
                queryClient.invalidateQueries([e.modelName]);
            });
            
        return () => {
            window.Echo.leaveChannel('digibase.activity');
        };
    }, []);
}
```

Real-time Features to Build:

1. Live Activity Feed (Dashboard):
   - Shows real-time events as they happen
   - Smooth animations
   - Event types:
     * Model created 🟢
     * Model updated 🟡
     * Model deleted 🔴
     * API call made 🔵
   - Each event shows:
     * Icon
     * Description
     * User
     * Timestamp
     * Click to view details
   - Auto-scroll to new items
   - Sound notification (optional)
   - Desktop notification (optional)

2. Live API Monitoring:
   - Real-time graph of API requests
   - Shows:
     * Requests per second
     * Response times
     * Error rate
     * Active users
   - Updates every second
   - Chart.js or Recharts
   - Smooth animations

3. Collaborative Indicators:
   - Show who's online
   - Show who's viewing what
   - Show who's editing (future)
   - Avatar list
   - "X users online" counter

4. Live Database Stats:
   - Updates when database changes
   - Shows:
     * Row counts
     * Table sizes
     * Recent changes
   - No need to refresh page

5. Instant Notifications:
   - Toast notifications
   - Types:
     * Success (green)
     * Info (blue)
     * Warning (yellow)
     * Error (red)
   - Shows on events
   - Auto-dismiss
   - Click to view details

6. Live Connection Status:
   - WebSocket connection indicator
   - Shows:
     * Connected ✓ (green)
     * Connecting... ⟳ (yellow)
     * Disconnected ✗ (red)
   - Auto-reconnect on disconnect
   - Show reconnection attempts

UI Components:

1. Activity Item Component:
```jsx
<div className="activity-item">
  <div className="icon">{getIcon(type)}</div>
  <div className="content">
    <p className="description">{description}</p>
    <p className="meta">
      <span className="user">{user}</span>
      <span className="time">{timeAgo}</span>
    </p>
  </div>
</div>
```

2. Connection Status Component:
```jsx
<div className="connection-status">
  {connected ? (
    <span className="connected">
      <CheckIcon /> Connected
    </span>
  ) : (
    <span className="disconnected">
      <XIcon /> Disconnected
    </span>
  )}
</div>
```

Advanced Features:

1. Presence Channels:
   - Track who's online
   - Track who's in which page
   - Show typing indicators (future)

2. Private Channels:
   - User-specific notifications
   - Project-specific events
   - Authenticated channels

3. Event Replay:
   - Store recent events
   - Replay missed events on reconnect
   - Event history

4. Rate Limiting:
   - Throttle events if too many
   - Batch updates
   - Prevent UI overload

Error Handling:
- Graceful degradation
- Fallback to polling if WebSocket fails
- Clear error messages
- Retry logic

Performance:
- Don't broadcast everything
- Debounce updates
- Batch notifications
- Optimize bundle size

Make real-time features SMOOTH and RELIABLE! ⚡
```

---

## Prompt 11: File Storage Manager

```
Create comprehensive file storage management system:

Backend - File Management:

1. Install Laravel Storage:
   - Already included
   - Configure storage disks in config/filesystems.php
   - Support: local, S3, DigitalOcean Spaces

2. File Model:
Create: App\Models\File

Fields:
- id
- name (original filename)
- path (storage path)
- disk (storage disk name)
- mime_type
- size (in bytes)
- user_id (who uploaded)
- folder_id (organization)
- metadata (JSON: dimensions, etc.)
- created_at
- updated_at

3. API Endpoints:

POST /api/storage/upload
- Accepts: multipart/form-data
- Max size: configurable (default 10MB)
- Allowed types: configurable
- Returns: file object with URL

GET /api/storage/files
- List all files
- Filters: folder, type, user
- Pagination
- Search by name

GET /api/storage/files/{id}
- Get single file details
- Returns download URL

DELETE /api/storage/files/{id}
- Deletes file from storage
- Deletes database record

POST /api/storage/folders
- Create folder
- Returns folder object

4. File Processing:
   - Image optimization (compress)
   - Thumbnail generation
   - Metadata extraction
   - Virus scanning (ClamAV optional)

5. Storage Strategies:
   - Local: public/storage
   - S3: AWS S3 bucket
   - DO Spaces: DigitalOcean
   - Configurable in settings

Frontend - File Manager Page:

Layout:
├── Sidebar (Left 20%)
│   ├── Storage stats
│   ├── Folder tree
│   └── Quick filters
│
├── Main Content (Center 60%)
│   ├── Toolbar
│   ├── File grid/list
│   └── Preview panel
│
└── Details Panel (Right 20%)
    ├── File info
    └── Actions

Features:

1. File Upload:
   - Drag-drop zone (full screen)
   - Click to browse
   - Multiple file upload
   - Upload progress bars
   - Cancel upload
   - Resume failed uploads
   - Paste to upload (clipboard)

2. File Browser:
   - View modes:
     * Grid (with thumbnails)
     * List (with details)
   - Sort by:
     * Name
     * Size
     * Date
     * Type
   - Filter by:
     * File type (images, documents, etc.)
     * Date range
     * Size range
   - Search by name
   - Select multiple files
   - Bulk actions

3. File Preview:
   - Images: full preview
   - PDFs: embedded viewer
   - Videos: video player
   - Audio: audio player
   - Text files: code viewer
   - Other: icon + download

4. Folder Management:
   - Create folder
   - Rename folder
   - Delete folder
   - Move files to folder
   - Folder breadcrumbs
   - Folder tree navigation

5. File Actions:
   - Rename file
   - Move to folder
   - Copy URL
   - Download file
   - Delete file
   - Share file (get public link)
   - View file details

6. Bulk Operations:
   - Select all
   - Select by type
   - Delete selected
   - Move selected
   - Download as ZIP

7. Storage Stats:
   - Total storage used
   - Available storage
   - Usage by type (pie chart)
   - Recent uploads

8. File Details Panel:
   Shows:
   - Thumbnail/preview
   - File name
   - File type
   - File size
   - Upload date
   - Uploaded by
   - Dimensions (for images)
   - Duration (for videos)
   - URL (copyable)
   - Actions buttons

UI Components:

1. Upload Zone:
```jsx
<div className="upload-zone">
  <input type="file" multiple hidden ref={inputRef} />
  <div className="dropzone" onClick={handleClick}>
    {isDragging ? (
      <div>Drop files here</div>
    ) : (
      <div>
        <UploadIcon />
        <p>Drag & drop or click to upload</p>
        <p className="hint">Max 10MB per file</p>
      </div>
    )}
  </div>
</div>
```

2. File Card (Grid View):
```jsx
<div className="file-card">
  <div className="thumbnail">
    {isImage ? <img src={url} /> : <FileIcon />}
  </div>
  <div className="info">
    <p className="name">{name}</p>
    <p className="size">{formatSize(size)}</p>
  </div>
  <div className="actions">
    <button>Copy URL</button>
    <button>Delete</button>
  </div>
</div>
```

3. Progress Bar:
```jsx
<div className="upload-progress">
  <div className="file-name">{file.name}</div>
  <div className="progress-bar">
    <div className="progress" style={{width: `${progress}%`}} />
  </div>
  <div className="progress-text">{progress}%</div>
  <button onClick={cancel}>Cancel</button>
</div>
```

Advanced Features:

1. Image Editing (Basic):
   - Crop
   - Resize
   - Rotate
   - Filters
   - Save as new file

2. CDN Integration:
   - CloudFlare CDN
   - BunnyCDN
   - Auto-generate CDN URLs

3. Image Optimization:
   - Auto-compress on upload
   - Generate multiple sizes
   - WebP conversion
   - Lazy loading URLs

4. Public Sharing:
   - Generate public links
   - Set expiry time
   - Password protect
   - View count

5. File Versioning:
   - Keep old versions
   - Restore previous version
   - Version history

Settings Configuration:

- Max file size
- Allowed file types
- Storage disk selection
- Auto-delete after X days
- Image optimization settings
- Thumbnail sizes

Design:
- Clean, modern interface
- Similar to: Dropbox, Google Drive
- Smooth animations
- Drag-drop friendly
- Keyboard shortcuts
- Responsive design

Make file management EFFORTLESS! 📁
```

---

## Prompt 12: Migration Manager

```
Build visual migration management interface:

Backend - Migration Management:

1. Migration Inspector Service:
Create: App\Services\MigrationInspectorService

Methods:
- getPendingMigrations()
  Returns list of migration files not yet run

- getRanMigrations()
  Returns list of executed migrations from DB

- getMigrationStatus()
  Returns overall migration status

- getMigrationContent($filename)
  Returns migration file content

2. Migration Runner Service:
Create: App\Services\MigrationRunnerService

Methods:
- runMigrations()
  Executes php artisan migrate
  Returns output and success status

- rollbackMigration($batch)
  Executes php artisan migrate:rollback
  Returns output

- runSpecificMigration($filename)
  Runs single migration

- getMigrationChanges($filename)
  Analyzes what migration will do

3. API Endpoints:

GET /api/migrations/status
Returns:
{
  "pending": [
    {
      "filename": "2024_01_01_000000_create_products_table.php",
      "name": "create_products_table",
      "created_at": "2024-01-01"
    }
  ],
  "ran": [
    {
      "migration": "2023_12_01_000000_create_users_table",
      "batch": 1,
      "ran_at": "2024-01-01 10:00:00"
    }
  ],
  "last_batch": 1
}

GET /api/migrations/{filename}
- Returns migration file content
- Returns analysis of changes

POST /api/migrations/run
- Runs pending migrations
- Returns output

POST /api/migrations/rollback
Body: { "batch": 1 }
- Rolls back specific batch
- Returns output

POST /api/migrations/refresh
- Rolls back all + re-runs all
- DANGEROUS - requires confirmation

Frontend - Migration Manager Page:

Layout:
├── Header
│   ├── Status summary
│   └── Actions
│
├── Tabs
│   ├── Tab 1: Pending
│   ├── Tab 2: Executed
│   └── Tab 3: History
│
└── Main Content
    ├── Migration list
    └── Details panel

Features:

1. Header Section:
   - Migration status badge:
     * All up-to-date ✓ (green)
     * Pending migrations ! (yellow)
     * Failed migrations ✗ (red)
   - Stats:
     * Total migrations
     * Pending count
     * Last run time
   - Main actions:
     * "Run Migrations" button (primary, large)
     * "Rollback" button (secondary)
     * "Refresh" button (danger)

2. Pending Migrations Tab:
   For each pending migration:
   - Migration name (readable)
   - Filename
   - Created date
   - Preview of changes
   - "View Code" button
   - Checkbox to select
   - Status: Pending 🟡
   
   Bottom actions:
   - "Run Selected" button
   - "Run All" button
   - Confirmation modal before run

3. Executed Migrations Tab:
   For each ran migration:
   - Migration name
   - Batch number
   - Run date & time
   - Run by (user)
   - Execution time
   - Status: Success ✓ (green)
   - "View Code" button
   
   Group by batch:
   - Batch 1
     - Migration 1
     - Migration 2
   - Batch 2
     - Migration 3
   
   Actions per batch:
   - "Rollback This Batch" button

4. History Tab:
   Timeline view:
   - All migration events
   - Types:
     * Migration ran
     * Migration rolled back
     * Migration failed
   - Each shows:
     * Date & time
     * User
     * Action
     * Result
   - Search history
   - Filter by date

5. Migration Details Modal:
   When clicking "View Code":
   - Modal opens
   - Shows:
     * Full migration code
     * Syntax highlighted
     * Tables affected
     * Columns added/removed
     * Indexes created
     * Foreign keys added
   - "Copy Code" button
   - "Close" button

6. Run Confirmation Modal:
   Before running migrations:
   - Modal with warning
   - List of migrations to run
   - Preview of changes
   - "This will modify your database"
   - Backup reminder
   - "Are you sure?" confirmation
   - Password verification (admin)
   - "Yes, Run Migrations" button
   - "Cancel" button

7. Rollback Confirmation:
   Before rolling back:
   - MORE serious warning
   - List of what will be undone
   - "This will DELETE tables/data"
   - Backup requirement
   - Type "ROLLBACK" to confirm
   - Admin password
   - "Yes, Rollback" button (red)

8. Progress Indicator:
   During migration:
   - Modal with progress
   - Shows:
     * Current migration running
     * Progress bar
     * Output log (live)
     * Success/error messages
   - Can't close until complete

9. Results Display:
   After migration completes:
   - Success message 🎉
   - Summary:
     * X migrations ran
     * Time taken
     * Tables created
     * All successful
   - Or error message:
     * Which migration failed
     * Error details
     * How to fix
     * Rollback suggestion
   - "Close" button

Safety Features:

1. Backup Warning:
   - Before ANY destructive action
   - "Have you backed up your database?"
   - Checkbox required
   - Link to backup instructions

2. Production Guard:
   - If APP_ENV=production
   - Extra confirmation required
   - Type full database name
   - Admin password required

3. Dry Run (Optional):
   - "Preview Changes" button
   - Shows what WOULD happen
   - Without executing

4. Auto-backup (Optional):
   - Before migration
   - Create database backup
   - Store backup file
   - Restore if fails

UI Components:

1. Migration Card:
```jsx
<div className="migration-card">
  <div className="migration-info">
    <h3>{name}</h3>
    <p className="filename">{filename}</p>
    <p className="date">{date}</p>
  </div>
  <div className="migration-preview">
    <p>Creates table: products</p>
    <p>Adds columns: name, price, description</p>
  </div>
  <div className="migration-actions">
    <button>View Code</button>
  </div>
</div>
```

2. Status Badge:
```jsx
<div className={`status-badge ${status}`}>
  {status === 'pending' && <ClockIcon />}
  {status === 'success' && <CheckIcon />}
  {status === 'failed' && <XIcon />}
  <span>{status}</span>
</div>
```

Advanced Features:

1. Migration Generator:
   - Create migrations from UI
   - Add columns visually
   - Generate file
   - Save to disk

2. Schema Diff:
   - Compare database to migrations
   - Show differences
   - Generate missing migrations

3. Migration Templates:
   - Common migration patterns
   - Quick start

4. Scheduling:
   - Schedule migrations for later
   - Run during off-hours

Error Handling:
- Clear error messages
- Suggest solutions
- Link to Laravel docs
- Support contact

Design:
- Professional developer tool feel
- Warning colors for dangerous actions
- Clear status indicators
- Real-time feedback
- Keyboard shortcuts

Make migration management SAFE and EASY! 🛡️
```

---

# ⚙️ Phase 6: Settings & Configuration (Day 13)

## Prompt 13: Comprehensive Settings Panel

```
Create full-featured settings management system:

Backend - Settings System:

1. Settings Model:
Create: App\Models\Setting

Fields:
- id
- key (unique)
- value (JSON)
- type (string, number, boolean, json)
- group (general, database, api, security, email)
- description
- is_public (can users see it)
- updated_at

2. Settings Service:
Create: App\Services\SettingsService

Methods:
- get($key, $default = null)
- set($key, $value)
- getGroup($group)
- setMultiple($settings)
- reset($key)
- resetGroup($group)

3. API Endpoints:

GET /api/settings
- Returns all settings (non-sensitive)
- Grouped by category

GET /api/settings/{group}
- Returns settings for specific group

PUT /api/settings
- Updates multiple settings
- Validates before saving

POST /api/settings/test-email
- Sends test email
- Validates SMTP settings

POST /api/settings/test-database
- Tests database connection
- Returns connection status

Frontend - Settings Page:

Layout:
├── Sidebar (Left 25%)
│   ├── Settings categories
│   └── Search
│
└── Main Content (Right 75%)
    ├── Category sections
    └── Save button

Settings Categories:

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. GENERAL SETTINGS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section: Project Information
- Project Name (text input)
- Project Description (textarea)
- Project URL (URL input, validated)
- Admin Email (email input)
- Support Email (email input)
- Timezone (dropdown, searchable)
- Date Format (dropdown)
- Time Format (12h/24h toggle)

Section: Appearance
- Logo Upload (image, max 2MB)
- Favicon Upload (image, .ico or .png)
- Primary Color (color picker)
- Dark Mode (toggle, default on)
- Language (dropdown: English, Sinhala)

Section: Localization
- Default Language
- Supported Languages (multi-select)
- Currency (dropdown)
- Number Format

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
2. DATABASE SETTINGS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section: Connection
- Database Type (MySQL, PostgreSQL, SQLite)
- Host (text input)
- Port (number input)
- Database Name (text input)
- Username (text input)
- Password (password input, toggleable)
- "Test Connection" button
  * Shows loading
  * Shows success/error
  * Validates before saving

Section: Backup
- Auto Backup (toggle)
- Backup Frequency (dropdown: Daily, Weekly, Monthly)
- Backup Time (time picker)
- Backup Retention (number, days)
- Backup Location (local, S3)
- "Backup Now" button
- "View Backups" link

Section: Performance
- Query Caching (toggle)
- Cache Driver (redis, memcached, file)
- Max Connections (number)
- Connection Timeout (seconds)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
3. API SETTINGS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section: General API
- Enable API (toggle, warning if disabled)
- API URL Prefix (text, default: /api)
- Default Response Format (JSON, XML)
- Pretty Print JSON (toggle)

Section: Rate Limiting
- Enable Rate Limiting (toggle)
- Requests per Minute (number slider, 0-1000)
- Requests per Hour (number slider)
- Requests per Day (number slider)
- Throttle Message (text)

Section: CORS
- Enable CORS (toggle)
- Allowed Origins (textarea, comma-separated)
  * Placeholder: https://example.com, https://app.example.com
- Allowed Methods (checkboxes: GET, POST, PUT, PATCH, DELETE)
- Allowed Headers (textarea)
- Allow Credentials (toggle)
- Max Age (number, seconds)

Section: API Keys
- List of API keys table:
  * Name
  * Key (hidden, click to reveal)
  * Created
  * Last Used
  * Actions (Copy, Regenerate, Delete)
- "Generate New API Key" button

Section: Versioning
- Enable API Versioning (toggle)
- Current Version (text, e.g., v1)
- Supported Versions (multi-select)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
4. SECURITY SETTINGS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section: Authentication
- Require Email Verification (toggle)
- Allow Registration (toggle)
- Password Min Length (number, default 8)
- Require Uppercase (toggle)
- Require Numbers (toggle)
- Require Special Characters (toggle)
- Password Expiry Days (number, 0 = never)

Section: Two-Factor Authentication
- Enable 2FA (toggle)
- Require 2FA for Admins (toggle)
- 2FA Method (SMS, Authenticator App, Email)

Section: Session
- Session Lifetime (minutes)
- Idle Timeout (minutes)
- Max Sessions per User (number)
- Remember Me Duration (days)

Section: IP Restrictions
- Enable IP Whitelist (toggle)
- Allowed IPs (textarea)
  * One per line
  * Supports CIDR notation
- Block on Violation (toggle)

Section: Failed Login
- Max Failed Attempts (number)
- Lockout Duration (minutes)
- Send Alert Email (toggle)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
5. EMAIL SETTINGS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section: SMTP Configuration
- Mail Driver (SMTP, Mailgun, SES, SendGrid)
- SMTP Host (text)
- SMTP Port (number, common: 587, 465, 25)
- Encryption (dropdown: TLS, SSL, None)
- Username (text)
- Password (password, toggleable)
- From Address (email)
- From Name (text)
- "Test Connection" button
  * Validates settings
  * Shows success/error
- "Send Test Email" button
  * Shows modal to enter recipient
  * Sends actual test email

Section: Email Templates
- Welcome Email (toggle)
- Password Reset (toggle)
- Email Verification (toggle)
- Notification Emails (toggle)
- "Edit Templates" button (future feature)

Section: Email Queue
- Use Queue for Emails (toggle)
- Queue Driver (sync, database, redis)
- Retry Failed (toggle)
- Max Retries (number)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
6. FEATURE TOGGLES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section: Core Features
- Real-time Updates (toggle)
  * Description: Enable WebSocket connections
- File Storage (toggle)
  * Description: Allow file uploads
- API Documentation (toggle)
  * Description: Show API docs page
- Code Generator (toggle)
  * Description: Enable code generation
- Model Creator (toggle)
  * Description: Visual model creator

Section: Advanced Features
- Database Backups (toggle)
- Migration Manager (toggle)
- API Testing Interface (toggle)
- Activity Logging (toggle)
- Performance Monitoring (toggle)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
7. STORAGE SETTINGS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section: Storage Driver
- Default Disk (dropdown: local, s3, spaces)
- Max Upload Size (number MB, slider)
- Allowed File Types (multi-select)
  * Images
  * Documents
  * Videos
  * Archives
  * Custom (text input)

Section: Local Storage
- Storage Path (text, default: storage/app/public)
- Visibility (public, private)

Section: AWS S3
- S3 Key (text)
- S3 Secret (password)
- S3 Region (dropdown)
- S3 Bucket (text)
- S3 URL (text, optional)

Section: DigitalOcean Spaces
- Spaces Key (text)
- Spaces Secret (password)
- Spaces Region (dropdown)
- Spaces Bucket (text)
- Spaces Endpoint (text)

Section: File Processing
- Auto Optimize Images (toggle)
- Max Image Dimensions (width x height)
- Generate Thumbnails (toggle)
- Thumbnail Sizes (textarea, e.g., 200x200, 400x400)
- WebP Conversion (toggle)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
8. NOTIFICATIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Section: Email Notifications
- New User Registered (toggle)
- Model Created (toggle)
- Error Occurred (toggle)
- Backup Completed (toggle)
- Send to Admin Email (toggle)

Section: Slack Integration
- Enable Slack (toggle)
- Webhook URL (text)
- Channel (text, default: #general)
- Events to Send (checkboxes)

Section: Discord Integration
- Enable Discord (toggle)
- Webhook URL (text)
- Events to Send (checkboxes)

UI Components:

1. Setting Input:
```jsx
<div className="setting-item">
  <div className="setting-label">
    <label>{label}</label>
    {description && (
      <p className="description">{description}</p>
    )}
  </div>
  <div className="setting-input">
    {renderInput(type, value, onChange)}
  </div>
</div>
```

2. Save Bar (Sticky Bottom):
```jsx
<div className="save-bar">
  <div className="changed-indicator">
    {hasChanges && (
      <span>You have unsaved changes</span>
    )}
  </div>
  <div className="actions">
    <button onClick={handleDiscard}>
      Discard Changes
    </button>
    <button onClick={handleSave} primary>
      Save Settings
    </button>
  </div>
</div>
```

3. Category Card:
```jsx
<div className="category-card">
  <div className="category-icon">{icon}</div>
  <div className="category-info">
    <h3>{title}</h3>
    <p>{description}</p>
  </div>
  <div className="category-status">
    {allConfigured ? <CheckIcon /> : <WarningIcon />}
  </div>
</div>
```

Features:

1. Change Detection:
   - Track which settings changed
   - Show unsaved indicator
   - Warn before leaving page
   - Save button sticky bottom

2. Validation:
   - Real-time validation
   - Show errors inline
   - Can't save if errors
   - Clear error messages

3. Test Buttons:
   - Test SMTP connection
   - Test database connection
   - Send test email
   - Shows loading
   - Shows success/error

4. Search:
   - Search all settings
   - Highlights results
   - Filters categories

5. Import/Export:
   - Export settings as JSON
   - Import settings from JSON
   - Backup settings

6. Reset Options:
   - Reset single setting
   - Reset category
   - Reset all (dangerous!)

Design:
- Clean, organized layout
- Clear sections
- Helpful descriptions
- Icons per category
- Consistent spacing
- Responsive
- Dark mode friendly

Make settings management COMPREHENSIVE and EASY! ⚙️
```

---

# 🛠️ Phase 7: CLI & Installer (Day 14)

## Prompt 14: One-Command Installer

```
Create bulletproof, user-friendly installer script:

File: install.sh

Requirements:
- Single command installation
- Interactive questions
- Error handling
- Progress indicators
- Works on Mac, Linux, Windows (Git Bash)

Script Structure:

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION 1: WELCOME & CHECKS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

```bash
#!/bin/bash

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Banner
echo -e "${BLUE}"
echo "╔═══════════════════════════════════════╗"
echo "║                                       ║"
echo "║         DIGIBASE INSTALLER            ║"
echo "║   The Laravel BaaS Platform           ║"
echo "║                                       ║"
echo "╚═══════════════════════════════════════╝"
echo -e "${NC}"

# System Checks
echo -e "${BLUE}[1/8]${NC} Checking system requirements..."

# Check PHP
if ! command -v php &> /dev/null; then
    echo -e "${RED}✗ PHP is not installed${NC}"
    echo "Please install PHP 8.2 or higher"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_VERSION;')
echo -e "${GREEN}✓ PHP ${PHP_VERSION} found${NC}"

# Check Composer
if ! command -v composer &> /dev/null; then
    echo -e "${RED}✗ Composer is not installed${NC}"
    echo "Please install Composer from https://getcomposer.org"
    exit 1
fi
echo -e "${GREEN}✓ Composer found${NC}"

# Check Node.js
if ! command -v node &> /dev/null; then
    echo -e "${RED}✗ Node.js is not installed${NC}"
    echo "Please install Node.js from https://nodejs.org"
    exit 1
fi

NODE_VERSION=$(node -v)
echo -e "${GREEN}✓ Node.js ${NODE_VERSION} found${NC}"

# Check npm
if ! command -v npm &> /dev/null; then
    echo -e "${RED}✗ npm is not installed${NC}"
    exit 1
fi
echo -e "${GREEN}✓ npm found${NC}"

# Check MySQL
if ! command -v mysql &> /dev/null; then
    echo -e "${YELLOW}⚠ MySQL not found in PATH${NC}"
    echo "Make sure MySQL is installed and running"
fi
```

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION 2: USER INPUT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

```bash
echo ""
echo -e "${BLUE}[2/8]${NC} Project Configuration"
echo ""

# Project Name
read -p "Enter project name (default: digibase): " PROJECT_NAME
PROJECT_NAME=${PROJECT_NAME:-digibase}

# Database Name
read -p "Enter database name (default: ${PROJECT_NAME}): " DB_NAME
DB_NAME=${DB_NAME:-$PROJECT_NAME}

# Database Host
read -p "Enter database host (default: localhost): " DB_HOST
DB_HOST=${DB_HOST:-localhost}

# Database Port
read -p "Enter database port (default: 3306): " DB_PORT
DB_PORT=${DB_PORT:-3306}

# Database Username
read -p "Enter database username (default: root): " DB_USERNAME
DB_USERNAME=${DB_USERNAME:-root}

# Database Password
read -sp "Enter database password: " DB_PASSWORD
echo ""

# Admin Email
read -p "Enter admin email: " ADMIN_EMAIL
while [[ ! "$ADMIN_EMAIL" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; do
    echo -e "${RED}Invalid email format${NC}"
    read -p "Enter admin email: " ADMIN_EMAIL
done

# Admin Password
read -sp "Enter admin password (min 8 characters): " ADMIN_PASSWORD
echo ""
while [[ ${#ADMIN_PASSWORD} -lt 8 ]]; do
    echo -e "${RED}Password must be at least 8 characters${NC}"
    read -sp "Enter admin password: " ADMIN_PASSWORD
    echo ""
done

# Confirm Password
read -sp "Confirm admin password: " ADMIN_PASSWORD_CONFIRM
echo ""
while [[ "$ADMIN_PASSWORD" != "$ADMIN_PASSWORD_CONFIRM" ]]; do
    echo -e "${RED}Passwords do not match${NC}"
    read -sp "Confirm admin password: " ADMIN_PASSWORD_CONFIRM
    echo ""
done

# Install Sample Data
read -p "Install example models? (y/n, default: y): " INSTALL_EXAMPLES
INSTALL_EXAMPLES=${INSTALL_EXAMPLES:-y}
```

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION 3: CLONE REPOSITORY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

```bash
echo ""
echo -e "${BLUE}[3/8]${NC} Downloading Digibase..."

# Create project directory
mkdir -p "$PROJECT_NAME"
cd "$PROJECT_NAME" || exit

# Clone repository
git clone https://github.com/yourusername/digibase.git .

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Failed to clone repository${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Digibase downloaded${NC}"
```

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION 4: BACKEND SETUP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

```bash
echo ""
echo -e "${BLUE}[4/8]${NC} Setting up backend..."

cd backend || exit

# Install Composer dependencies
echo "Installing PHP dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Composer install failed${NC}"
    exit 1
fi

# Copy .env file
cp .env.example .env

# Update .env file
sed -i.bak "s/DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i.bak "s/DB_HOST=.*/DB_HOST=${DB_HOST}/" .env
sed -i.bak "s/DB_PORT=.*/DB_PORT=${DB_PORT}/" .env
sed -i.bak "s/DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME}/" .env
sed -i.bak "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" .env
rm .env.bak

# Generate application key
php artisan key:generate --no-interaction

# Test database connection
echo "Testing database connection..."
php artisan migrate:status &> /dev/null

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ Database connection failed${NC}"
    echo "Please check your database credentials"
    exit 1
fi

echo -e "${GREEN}✓ Database connection successful${NC}"

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Create admin user
echo "Creating admin user..."
php artisan digibase:create-admin \
    --email="$ADMIN_EMAIL" \
    --password="$ADMIN_PASSWORD" \
    --no-interaction

# Install example models
if [[ "$INSTALL_EXAMPLES" =~ ^[Yy]$ ]]; then
    echo "Installing example models..."
    php artisan digibase:install-examples
fi

# Link storage
php artisan storage:link

echo -e "${GREEN}✓ Backend setup complete${NC}"

cd ..
```

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION 5: FRONTEND SETUP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

```bash
echo ""
echo -e "${BLUE}[5/8]${NC} Setting up frontend..."

cd frontend || exit

# Copy .env file
cp .env.example .env

# Update frontend .env
cat > .env << EOF
VITE_API_URL=http://localhost:8000
VITE_API_BASE_URL=http://localhost:8000/api
EOF

# Install npm dependencies
echo "Installing JavaScript dependencies..."
npm install

if [ $? -ne 0 ]; then
    echo -e "${RED}✗ npm install failed${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Frontend setup complete${NC}"

cd ..
```

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION 6: FINAL CONFIGURATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

```bash
echo ""
echo -e "${BLUE}[6/8]${NC} Final configuration..."

# Create start script
cat > start.sh << 'EOF'
#!/bin/bash
echo "Starting Digibase..."
cd backend && php artisan serve &
cd frontend && npm run dev &
echo "Digibase is running!"
echo "Backend: http://localhost:8000"
echo "Frontend: http://localhost:5173"
EOF

chmod +x start.sh

# Create stop script
cat > stop.sh << 'EOF'
#!/bin/bash
echo "Stopping Digibase..."
pkill -f "php artisan serve"
pkill -f "vite"
echo "Digibase stopped"
EOF

chmod +x stop.sh

echo -e "${GREEN}✓ Scripts created${NC}"
```

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION 7: START SERVERS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

```bash
echo ""
echo -e "${BLUE}[7/8]${NC} Starting development servers..."

# Start backend
cd backend
php artisan serve > /dev/null 2>&1 &
BACKEND_PID=$!
echo -e "${GREEN}✓ Backend started (PID: $BACKEND_PID)${NC}"

# Wait for backend to start
sleep 3

# Start frontend
cd ../frontend
npm run dev > /dev/null 2>&1 &
FRONTEND_PID=$!
echo -e "${GREEN}✓ Frontend started (PID: $FRONTEND_PID)${NC}"

# Wait for frontend to start
sleep 5

cd ..
```

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SECTION 8: SUCCESS MESSAGE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

```bash
echo ""
echo -e "${GREEN}"
echo "╔═══════════════════════════════════════╗"
echo "║                                       ║"
echo "║   🎉 INSTALLATION SUCCESSFUL! 🎉     ║"
echo "║                                       ║"
echo "╚═══════════════════════════════════════╝"
echo -e "${NC}"
echo ""
echo -e "${BLUE}Your Digibase installation is ready!${NC}"
echo ""
echo -e "${GREEN}URLs:${NC}"
echo "  Backend:  http://localhost:8000"
echo "  Frontend: http://localhost:5173"
echo "  Admin:    http://localhost:5173/admin"
echo ""
echo -e "${GREEN}Admin Credentials:${NC}"
echo "  Email:    $ADMIN_EMAIL"
echo "  Password: ********"
echo ""
echo -e "${GREEN}Quick Commands:${NC}"
echo "  Start:    ./start.sh"
echo "  Stop:     ./stop.sh"
echo ""
echo -e "${BLUE}Next Steps:${NC}"
echo "  1. Visit http://localhost:5173"
echo "  2. Login with your admin credentials"
echo "  3. Create your first model"
echo "  4. Check out the documentation"
echo ""
echo -e "${YELLOW}Need help?${NC}"
echo "  Docs:     https://docs.digibase.dev"
echo "  Discord:  https://discord.gg/digibase"
echo "  GitHub:   https://github.com/yourusername/digibase"
echo ""

# Open browser
if command -v open &> /dev/null; then
    open http://localhost:5173
elif command -v xdg-open &> /dev/null; then
    xdg-open http://localhost:5173
elif command -v start &> /dev/null; then
    start http://localhost:5173
fi
```

Additional Features:

1. Error Recovery:
   - If step fails, show clear error
   - Suggest solution
   - Option to retry
   - Rollback on critical failure

2. Progress Indicators:
   - Show step numbers
   - Show current action
   - Estimated time remaining
   - Spinners for long operations

3. Logging:
   - Save install log to file
   - Include in error reports
   - Useful for debugging

4. Update Script:
   - update.sh for updates
   - Backup before update
   - Run migrations
   - Rebuild frontend

Usage:

```bash
# Quick install
bash <(curl -s https://digibase.dev/install.sh)

# Or download first
curl -O https://digibase.dev/install.sh
chmod +x install.sh
./install.sh
```

Make installation EFFORTLESS! 🚀
```

---

# 📚 Phase 8: Documentation (Day 15)

*Content continues but message is getting very long...*

Would you like me to:
1. Continue with Phases 8-10 in this file
2. Create a separate file for remaining phases
3. Or provide the complete file with all phases now?

Let me know and I'll complete the documentation! 😊
