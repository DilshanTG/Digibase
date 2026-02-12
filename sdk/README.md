# Digibase TypeScript SDK v1.0

A robust, type-safe TypeScript SDK for interacting with the Digibase API. Built for Next.js and modern JavaScript applications.

## 📦 Installation

Copy the `sdk/` folder into your Next.js project:

```bash
cp -r sdk/ /path/to/your/nextjs-project/lib/digibase
```

## 🚀 Quick Start

```typescript
import { createClient } from './lib/digibase';

// Initialize the client
const digibase = createClient(
  'https://your-api.com',
  'pk_your_api_key_here'
);

// Query data
const { data } = await digibase
  .from('mobile_phones')
  .select('id,Phone name,model')
  .limit(10)
  .get();

console.log(data);
```

## 🎯 Core Features

- ✅ **Type-Safe**: Full TypeScript support with generics
- ✅ **Fluent API**: Chainable query builder
- ✅ **CRUD Operations**: Create, Read, Update, Delete
- ✅ **Bulk Operations**: Insert multiple records at once
- ✅ **File Uploads**: Built-in storage management
- ✅ **Filtering & Sorting**: Advanced query capabilities
- ✅ **Pagination**: Easy page navigation

## 📚 API Reference

### 1. Initialize Client

```typescript
import { createClient } from './lib/digibase';

const digibase = createClient(
  'https://api.yourdomain.com',
  'pk_your_api_key'
);
```

### 2. Query Data (GET)

#### Get All Records
```typescript
const { data } = await digibase
  .from('mobile_phones')
  .get();
```

#### Get Single Record
```typescript
const { data } = await digibase
  .from('mobile_phones')
  .find(1);
```

#### Select Specific Columns
```typescript
const { data } = await digibase
  .from('mobile_phones')
  .select('id,Phone name,Year')
  .get();
```

#### Filter Records
```typescript
const { data } = await digibase
  .from('mobile_phones')
  .eq('Year', '2024')
  .get();
```

#### Sort Results
```typescript
const { data } = await digibase
  .from('mobile_phones')
  .sort('created_at', 'desc')
  .get();
```

#### Pagination
```typescript
const { data, meta } = await digibase
  .from('mobile_phones')
  .page(1)
  .limit(20)
  .get();

console.log(meta.current_page);
console.log(meta.total);
```

#### Chain Multiple Operations
```typescript
const { data } = await digibase
  .from('mobile_phones')
  .select('id,Phone name,model')
  .eq('Year', '2024')
  .sort('Phone name', 'asc')
  .page(1)
  .limit(10)
  .get();
```

### 3. Insert Data (POST)

#### Single Insert
```typescript
const { data } = await digibase
  .from('mobile_phones')
  .insert({
    'Phone name': 'iPhone 15',
    model: 'Pro Max',
    Year: '2024'
  });
```

#### Bulk Insert
```typescript
const { data } = await digibase
  .from('mobile_phones')
  .bulkInsert([
    { 'Phone name': 'iPhone 15', model: 'Pro', Year: '2024' },
    { 'Phone name': 'Samsung S24', model: 'Ultra', Year: '2024' },
    { 'Phone name': 'Google Pixel 9', model: 'Pro', Year: '2024' }
  ]);

console.log(data.count); // 3
```

### 4. Update Data (PUT)

```typescript
const { data } = await digibase
  .from('mobile_phones')
  .update(1, {
    'Phone name': 'iPhone 15 Pro Max',
    Year: '2024'
  });
```

### 5. Delete Data (DELETE)

```typescript
const { data } = await digibase
  .from('mobile_phones')
  .delete(1);
```

### 6. File Uploads

#### Upload File
```typescript
// In a Next.js component
const handleUpload = async (file: File) => {
  const { data } = await digibase.storage.upload(file, 'products');

  console.log(data.url);  // Full URL to access the file
  console.log(data.path); // Storage path
  console.log(data.type); // MIME type
  console.log(data.size); // File size in bytes

  // Save the URL to your database
  await digibase.from('products').insert({
    name: 'Product 1',
    image: data.url
  });
};
```

#### Delete File
```typescript
await digibase.storage.delete('products/abc123.jpg');
```

## 🎨 TypeScript Usage

### Define Your Models

```typescript
interface MobilePhone {
  id: number;
  'Phone name': string;
  model: string;
  Year: string;
  Image?: string;
  created_at: string;
  updated_at: string;
}

// Use with type safety
const { data } = await digibase
  .from<MobilePhone>('mobile_phones')
  .get();

// data is now typed as MobilePhone[]
data.forEach(phone => {
  console.log(phone['Phone name']); // TypeScript knows this exists
});
```

### Generic Query
```typescript
const query = digibase.from<MobilePhone>('mobile_phones');

// All methods are now type-safe
const result = await query.insert({
  'Phone name': 'Test', // TypeScript validates this
  model: 'Test Model',
  Year: '2024'
});
```

## 📝 Complete Example (Next.js)

```typescript
// lib/digibase/client.ts
import { createClient } from './sdk';

export const digibase = createClient(
  process.env.NEXT_PUBLIC_API_URL!,
  process.env.NEXT_PUBLIC_API_KEY!
);

// app/phones/page.tsx
'use client';

import { useState, useEffect } from 'react';
import { digibase } from '@/lib/digibase/client';

interface Phone {
  id: number;
  'Phone name': string;
  model: string;
  Year: string;
}

export default function PhonesPage() {
  const [phones, setPhones] = useState<Phone[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchPhones = async () => {
      try {
        const { data } = await digibase
          .from<Phone>('mobile_phones')
          .sort('created_at', 'desc')
          .limit(10)
          .get();

        setPhones(data);
      } catch (error) {
        console.error('Failed to fetch phones:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchPhones();
  }, []);

  if (loading) return <div>Loading...</div>;

  return (
    <div>
      <h1>Mobile Phones</h1>
      {phones.map(phone => (
        <div key={phone.id}>
          <h2>{phone['Phone name']}</h2>
          <p>Model: {phone.model}</p>
          <p>Year: {phone.Year}</p>
        </div>
      ))}
    </div>
  );
}
```

## 🔐 Environment Variables

Create a `.env.local` file in your Next.js project:

```env
NEXT_PUBLIC_API_URL=https://your-api-domain.com
NEXT_PUBLIC_API_KEY=pk_your_api_key_here
```

## 🛡️ Error Handling

```typescript
try {
  const { data, error } = await digibase
    .from('mobile_phones')
    .get();

  if (error) {
    console.error('API Error:', error);
    return;
  }

  console.log('Success:', data);
} catch (err) {
  console.error('Network Error:', err);
}
```

## 📊 Response Format

All API responses follow this structure:

```typescript
interface ApiResponse<T> {
  data: T;
  meta?: {
    current_page?: number;
    last_page?: number;
    total?: number;
  };
  message?: string;
  error?: string;
}
```

## 🎯 Advanced Patterns

### Custom Hook (React)
```typescript
// hooks/useDigibase.ts
import { useState, useEffect } from 'react';
import { digibase } from '@/lib/digibase/client';

export function useDigibase<T>(table: string) {
  const [data, setData] = useState<T[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetch = async () => {
      try {
        const result = await digibase.from<T>(table).get();
        setData(result.data);
      } catch (err: any) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetch();
  }, [table]);

  return { data, loading, error };
}

// Usage
const { data: phones, loading, error } = useDigibase<Phone>('mobile_phones');
```

### Server Components (Next.js 13+)
```typescript
// app/phones/page.tsx
import { digibase } from '@/lib/digibase/client';

export default async function PhonesPage() {
  const { data: phones } = await digibase
    .from('mobile_phones')
    .get();

  return (
    <div>
      {phones.map(phone => (
        <div key={phone.id}>{phone['Phone name']}</div>
      ))}
    </div>
  );
}
```

## 📄 License

MIT License - Part of the Digibase project

## 🤝 Contributing

This SDK is auto-generated for the Digibase API. For issues or feature requests, please contact the Digibase team.

---

**Built with ❤️ for Next.js developers**
