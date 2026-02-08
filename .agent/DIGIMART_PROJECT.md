# DigiMart - Digital Subscription Store
## Complete Project Specification for AI Development

---

## 🎯 PROJECT OVERVIEW

**What:** E-commerce store for selling digital subscriptions (Netflix, Spotify, ChatGPT, Adobe, etc.)

**Business Model:**
- Sell shared accounts, invites, activation keys
- Auto-delivery after payment
- Guest checkout (no registration needed)
- 3-click purchase flow

**Target:** Sri Lankan market (LKR currency)

---

## 🛠️ TECH STACK

| Layer | Technology |
|-------|------------|
| Frontend | React 18 + TypeScript |
| Build Tool | Vite |
| Styling | Tailwind CSS |
| UI Components | shadcn/ui |
| State Management | Zustand (cart) |
| Data Fetching | TanStack Query (React Query) |
| Routing | React Router v6 |
| Backend | Supabase (PostgreSQL + Auth + Edge Functions) |
| Charts | Recharts |
| Hosting | Vercel / Netlify |

---

## 🔑 CONFIGURATION KEYS

```env
# Supabase
VITE_SUPABASE_URL=https://ypehszsaombqjgfmlsva.supabase.co
VITE_SUPABASE_ANON_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InlwZWhzenNhb21icWpnZm1sc3ZhIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Njg5NzI4MDgsImV4cCI6MjA4NDU0ODgwOH0.LlbrBSpVHdZU7nk9Zh_JLfXiPajjfGjUncx_giELAfA

# DigiMart Pay (Payment Gateway)
DIGIMART_API_KEY=sk_live_cba6a41e6a202fa791cfff218600613c
DIGIMART_SECRET_KEY=d60223bbe2ba9aedcd44708e9986d3c20d5a86a5657e3903a7e49cb7b03d5d10

# Supabase Storage S3
S3_ENDPOINT=https://ypehszsaombqjgfmlsva.storage.supabase.co/storage/v1/s3
S3_ACCESS_KEY=c094d2ac8324817e722719a37aeedff8
S3_REGION=ap-south-1

# Internal Keys
SB_SECRET_KEY=sb_secret_iM9zJgSvZkEUGgA8IYLDug_rDZFI-eh
SB_PUBLISHABLE_KEY=sb_publishable_ZiwiUGMDRQJ0mnl1a1y_hw_MUU8gjsk
```

---

## 📦 PRODUCT TYPES TO SELL

| Type | Description | Delivery Method |
|------|-------------|-----------------|
| Shared Accounts | Netflix, Spotify shared login | Email + Password |
| Invites | Private platform invitations | Invite code/link |
| Own Mail Upgrades | Upgrade to customer's email | Manual activation |
| Auto Delivery | Instant credential delivery | Automatic |
| Manual Delivery | Admin sends manually | Manual |
| Digital Downloads | Files (PDF, ZIP, etc.) | Download link |
| Link Access | Access via URL | URL delivery |

---

## 🗄️ DATABASE SCHEMA

### Table: `categories`
```sql
CREATE TABLE categories (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  slug TEXT UNIQUE NOT NULL,
  description TEXT,
  icon TEXT,
  sort_order INTEGER DEFAULT 0,
  is_active BOOLEAN DEFAULT true,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `products`
```sql
CREATE TABLE products (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  description TEXT,
  image_url TEXT,
  category_id UUID REFERENCES categories(id),
  is_active BOOLEAN DEFAULT true,
  is_featured BOOLEAN DEFAULT false,
  is_external BOOLEAN DEFAULT false,
  external_url TEXT,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `product_variants`
```sql
CREATE TABLE product_variants (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID REFERENCES products(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  price NUMERIC NOT NULL,
  original_price NUMERIC,
  duration_days INTEGER,
  subscription_type TEXT CHECK (subscription_type IN ('premade_account', 'email_invite', 'login_activation', 'key_based')),
  fulfillment_mode TEXT CHECK (fulfillment_mode IN ('auto', 'manual')) DEFAULT 'auto',
  stock_count INTEGER DEFAULT 0,
  is_active BOOLEAN DEFAULT true,
  sort_order INTEGER DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `premade_accounts`
```sql
CREATE TABLE premade_accounts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID REFERENCES products(id),
  variant_id UUID REFERENCES product_variants(id),
  email TEXT NOT NULL,
  password TEXT NOT NULL,
  additional_info TEXT,
  is_used BOOLEAN DEFAULT false,
  order_id UUID,
  used_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `activation_keys`
```sql
CREATE TABLE activation_keys (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID REFERENCES products(id),
  variant_id UUID REFERENCES product_variants(id),
  key_code TEXT NOT NULL,
  redemption_url TEXT,
  additional_info TEXT,
  is_used BOOLEAN DEFAULT false,
  order_id UUID,
  used_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `stock_stacks`
```sql
CREATE TABLE stock_stacks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID REFERENCES products(id),
  variant_id UUID REFERENCES product_variants(id),
  stack_name TEXT,
  accounts_data TEXT,
  stock_type TEXT CHECK (stock_type IN ('account', 'key')),
  total_count INTEGER DEFAULT 0,
  used_count INTEGER DEFAULT 0,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `pending_orders`
```sql
CREATE TABLE pending_orders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_number TEXT UNIQUE NOT NULL,
  payment_reference TEXT,
  customer_name TEXT NOT NULL,
  customer_email TEXT NOT NULL,
  customer_whatsapp TEXT NOT NULL,
  product_id UUID REFERENCES products(id),
  variant_id UUID REFERENCES product_variants(id),
  amount NUMERIC NOT NULL,
  payment_method TEXT,
  payment_status TEXT CHECK (payment_status IN ('unpaid', 'paid', 'failed')) DEFAULT 'unpaid',
  notes TEXT,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `orders`
```sql
CREATE TABLE orders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  order_number TEXT UNIQUE NOT NULL,
  payment_reference TEXT,
  customer_name TEXT NOT NULL,
  customer_email TEXT NOT NULL,
  customer_whatsapp TEXT NOT NULL,
  product_id UUID REFERENCES products(id),
  variant_id UUID REFERENCES product_variants(id),
  amount NUMERIC NOT NULL,
  payment_method TEXT,
  status TEXT CHECK (status IN ('completed', 'failed', 'inactive')) DEFAULT 'completed',
  delivery_data JSONB,
  delivered_at TIMESTAMPTZ,
  notes TEXT,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `customers`
```sql
CREATE TABLE customers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  name TEXT NOT NULL,
  email TEXT UNIQUE NOT NULL,
  whatsapp TEXT,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `reviews`
```sql
CREATE TABLE reviews (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  product_id UUID REFERENCES products(id),
  customer_id UUID REFERENCES customers(id),
  order_id UUID REFERENCES orders(id),
  rating INTEGER CHECK (rating >= 1 AND rating <= 5),
  comment TEXT,
  customer_name TEXT,
  customer_email TEXT,
  is_approved BOOLEAN DEFAULT false,
  is_hidden BOOLEAN DEFAULT false,
  admin_reply TEXT,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

### Table: `settings`
```sql
CREATE TABLE settings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  key TEXT UNIQUE NOT NULL,
  value TEXT,
  created_at TIMESTAMPTZ DEFAULT now(),
  updated_at TIMESTAMPTZ DEFAULT now()
);
```

---

## 🛣️ ROUTING STRUCTURE

### Public Routes (Customer)
```
/                           → Homepage (featured products)
/products                   → All products listing
/products/:id               → Product detail page
/category/:slug             → Category filtered products
/cart                       → Shopping cart
/checkout                   → Checkout form (name, email, whatsapp)
/payment                    → Payment processing
/payment/success/:orderId   → Success + credentials display
/payment/failed             → Payment failed page
/order-tracking             → Track order by order number
```

### Admin Routes (Protected)
```
/admin                      → Redirect to dashboard
/admin/login                → Admin login page
/admin/dashboard            → Analytics dashboard
/admin/orders               → Pending orders (approve/reject)
/admin/subscriptions        → Active orders management
/admin/products             → Product CRUD
/admin/stock-stacks         → Stock & inventory management
/admin/customers            → Customer list
/admin/reviews              → Review moderation
/admin/categories           → Category management
/admin/settings             → System settings
/admin/reporting-hub        → Reports & analytics
```

---

## 🎨 UI/UX REQUIREMENTS

### Design Principles
- **Mobile-first** - 100% responsive
- **Sales optimized** - Clear CTAs, trust badges
- **Minimal clicks** - 3-click checkout max
- **Fast loading** - Lazy load images
- **Modern aesthetic** - Clean, premium look

### Color Scheme (Suggestion)
```css
--primary: #6366f1    /* Indigo */
--secondary: #f59e0b  /* Amber */
--success: #10b981    /* Green */
--danger: #ef4444     /* Red */
--background: #0f172a /* Dark slate */
--card: #1e293b       /* Slate */
--text: #f8fafc       /* White */
```

### Required Components
- [ ] Navbar (logo, search, cart icon with count)
- [ ] Hero section (featured banner)
- [ ] Product card (image, name, price, rating, add to cart)
- [ ] Product grid (responsive)
- [ ] Category filter sidebar/tabs
- [ ] Cart drawer/page
- [ ] Checkout form (3 fields only)
- [ ] Payment status pages
- [ ] Order tracking form
- [ ] Footer (links, WhatsApp contact)
- [ ] Admin sidebar navigation
- [ ] Data tables (orders, products, etc.)
- [ ] Charts (revenue, orders)
- [ ] Stock management forms
- [ ] Toast notifications

---

## 🔄 CUSTOMER FLOW

```
1. Browse → 2. Select Product → 3. Choose Variant → 4. Add to Cart
     ↓
5. Checkout (name, email, whatsapp) → 6. Payment Gateway
     ↓
7. Webhook confirms payment → 8. Auto-fulfill credentials
     ↓
9. Success page shows credentials → 10. Email delivery (optional)
```

### Checkout Form Fields (ONLY 3!)
```typescript
interface CheckoutForm {
  customerName: string;     // Required
  customerEmail: string;    // Required
  customerWhatsapp: string; // Required
}
```

---

## 🔐 SECURITY REQUIREMENTS

### Row Level Security (RLS)
```sql
-- Products: Public read, Admin write
-- Orders: Admin only
-- Stock (premade_accounts, activation_keys): Admin only
-- Settings: Admin only
-- Customers: Admin only
-- Reviews: Public read (approved), Admin write
```

### Payment Security
1. **VERIFY WEBHOOK SIGNATURE** - MD5 hash validation
2. **VERIFY AMOUNT** - Match payment with order amount
3. **PREVENT REPLAY** - Check if already processed
4. **SERVER-SIDE PRICES** - Never trust client prices

### Admin Auth
- Real password authentication
- Session management via Supabase Auth
- Role check on every admin route

---

## ⚡ EDGE FUNCTIONS

### 1. `digimart-pay-init`
**Purpose:** Initialize payment with gateway
```typescript
// Input
{ order_number, amount, customer_email, customer_name }

// Output
{ paymentUrl: "https://gateway.com/pay/..." }
```

### 2. `digimart-pay-webhook`
**Purpose:** Receive payment confirmation
```typescript
// Input (from gateway)
{ merchant_id, order_id, amount, status, payment_method, transaction_id, signature }

// Process
1. Verify signature
2. Update pending_order to paid
3. Trigger auto-fulfill
```

### 3. `auto-fulfill`
**Purpose:** Deliver credentials automatically
```typescript
// Trigger: payment_status = 'paid'

// Process
1. Find unused stock (account/key)
2. Mark as used
3. Create order with delivery_data
4. Delete from pending_orders
```

---

## 📁 PROJECT STRUCTURE

```
src/
├── components/
│   ├── ui/              # shadcn/ui components
│   ├── layout/          # Navbar, Footer, Sidebar
│   ├── products/        # ProductCard, ProductGrid
│   ├── cart/            # CartDrawer, CartItem
│   ├── checkout/        # CheckoutForm
│   └── admin/           # Admin components
├── pages/
│   ├── Home.tsx
│   ├── Products.tsx
│   ├── ProductDetail.tsx
│   ├── Cart.tsx
│   ├── Checkout.tsx
│   ├── Payment.tsx
│   ├── PaymentSuccess.tsx
│   ├── PaymentFailed.tsx
│   ├── OrderTracking.tsx
│   └── admin/
│       ├── Dashboard.tsx
│       ├── Orders.tsx
│       ├── Subscriptions.tsx
│       ├── Products.tsx
│       ├── StockStacks.tsx
│       ├── Customers.tsx
│       ├── Reviews.tsx
│       ├── Categories.tsx
│       ├── Settings.tsx
│       └── ReportingHub.tsx
├── hooks/
│   ├── useProducts.ts
│   ├── useCart.ts
│   ├── useOrders.ts
│   └── useAuth.ts
├── store/
│   └── cartStore.ts     # Zustand cart
├── lib/
│   ├── supabase.ts      # Supabase client
│   └── utils.ts
├── types/
│   └── index.ts         # TypeScript types
└── App.tsx
```

---

## 🎯 DEVELOPMENT PRIORITIES

### Phase 1: Core Store (MVP)
1. [ ] Project setup (Vite + React + TypeScript)
2. [ ] Supabase connection
3. [ ] Database tables creation
4. [ ] Homepage with products
5. [ ] Product detail page
6. [ ] Cart functionality
7. [ ] Checkout flow
8. [ ] Payment integration

### Phase 2: Admin Panel
1. [ ] Admin authentication
2. [ ] Dashboard
3. [ ] Orders management
4. [ ] Products CRUD
5. [ ] Stock management

### Phase 3: Polish
1. [ ] Reviews system
2. [ ] Analytics/Reports
3. [ ] Email notifications
4. [ ] Performance optimization
5. [ ] Security hardening

---

## 🚀 AI CODING RULES

```
RULES FOR AI:
- Short code, maximum output
- No long explanations
- Save context, remember everything
- Use shadcn/ui components
- Mobile-first responsive
- TypeScript strict mode
- Follow project structure
- Test each feature
- Handle errors gracefully

WORKFLOW:
1. Make plan
2. Show plan
3. Execute plan
4. Run and test
```

---

## ✅ QUICK START COMMANDS

```bash
# Create project
npm create vite@latest digimart -- --template react-ts

# Install dependencies
npm install @supabase/supabase-js @tanstack/react-query zustand react-router-dom recharts

# Install shadcn/ui
npx shadcn-ui@latest init

# Add components
npx shadcn-ui@latest add button card input label toast dialog table tabs

# Run dev server
npm run dev
```

---

## 📞 CONTACT INTEGRATION

**WhatsApp Business:** Integrate floating WhatsApp button
```
https://wa.me/94XXXXXXXXX?text=Hi, I need help with my order
```

---

**END OF SPECIFICATION**

*Use this document as the single source of truth for AI development.*
