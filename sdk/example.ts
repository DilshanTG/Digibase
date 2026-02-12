/**
 * Digibase SDK - Usage Examples
 *
 * This file demonstrates all core features of the Digibase TypeScript SDK.
 * Copy and adapt these examples to your Next.js application.
 */

import { createClient } from './index';

// ============================================================================
// INITIALIZATION
// ============================================================================

const digibase = createClient(
  'https://your-api-domain.com',
  'pk_your_api_key_here'
);

// ============================================================================
// DEFINE YOUR TYPES (OPTIONAL BUT RECOMMENDED)
// ============================================================================

interface MobilePhone {
  id: number;
  'Phone name': string;
  model: string;
  Year: string;
  Image?: string;
  created_at: string;
  updated_at: string;
}

interface Product {
  id: number;
  name: string;
  price: number;
  description?: string;
  image_url?: string;
  stock: number;
}

// ============================================================================
// EXAMPLE 1: GET ALL RECORDS
// ============================================================================

async function getAllPhones() {
  const { data, meta } = await digibase
    .from<MobilePhone>('mobile_phones')
    .get();

  console.log('Total phones:', meta?.total);
  console.log('Phones:', data);

  return data;
}

// ============================================================================
// EXAMPLE 2: GET SINGLE RECORD BY ID
// ============================================================================

async function getPhoneById(id: number) {
  const { data } = await digibase
    .from<MobilePhone>('mobile_phones')
    .find(id);

  console.log('Phone:', data);
  return data;
}

// ============================================================================
// EXAMPLE 3: FILTERING & SORTING
// ============================================================================

async function getFilteredPhones() {
  const { data } = await digibase
    .from<MobilePhone>('mobile_phones')
    .select('id,Phone name,model,Year')
    .eq('Year', '2024')
    .sort('Phone name', 'asc')
    .limit(10)
    .get();

  console.log('2024 Phones:', data);
  return data;
}

// ============================================================================
// EXAMPLE 4: PAGINATION
// ============================================================================

async function getPaginatedPhones(page: number = 1) {
  const { data, meta } = await digibase
    .from<MobilePhone>('mobile_phones')
    .page(page)
    .limit(20)
    .sort('created_at', 'desc')
    .get();

  console.log(`Page ${meta?.current_page} of ${meta?.last_page}`);
  console.log(`Total records: ${meta?.total}`);

  return { data, meta };
}

// ============================================================================
// EXAMPLE 5: INSERT SINGLE RECORD
// ============================================================================

async function createPhone() {
  const { data, message } = await digibase
    .from<MobilePhone>('mobile_phones')
    .insert({
      'Phone name': 'iPhone 15 Pro Max',
      model: 'A2849',
      Year: '2024'
    });

  console.log('Created:', data);
  console.log('Message:', message);

  return data;
}

// ============================================================================
// EXAMPLE 6: BULK INSERT
// ============================================================================

async function bulkCreatePhones() {
  const phones = [
    { 'Phone name': 'iPhone 15', model: 'Pro', Year: '2024' },
    { 'Phone name': 'Samsung S24', model: 'Ultra', Year: '2024' },
    { 'Phone name': 'Google Pixel 9', model: 'Pro', Year: '2024' },
    { 'Phone name': 'OnePlus 12', model: 'Pro', Year: '2024' }
  ];

  const { data } = await digibase
    .from<MobilePhone>('mobile_phones')
    .bulkInsert(phones);

  console.log(`Inserted ${data.count} records`);

  return data;
}

// ============================================================================
// EXAMPLE 7: UPDATE RECORD
// ============================================================================

async function updatePhone(id: number) {
  const { data } = await digibase
    .from<MobilePhone>('mobile_phones')
    .update(id, {
      'Phone name': 'iPhone 15 Pro Max (Updated)',
      Year: '2024'
    });

  console.log('Updated:', data);
  return data;
}

// ============================================================================
// EXAMPLE 8: DELETE RECORD
// ============================================================================

async function deletePhone(id: number) {
  const { data, message } = await digibase
    .from<MobilePhone>('mobile_phones')
    .delete(id);

  console.log('Deleted:', message);
  return data;
}

// ============================================================================
// EXAMPLE 9: FILE UPLOAD
// ============================================================================

async function uploadProductImage(file: File) {
  // Upload to 'products' folder
  const { data } = await digibase.storage.upload(file, 'products');

  console.log('Uploaded file URL:', data.url);
  console.log('File path:', data.path);
  console.log('File type:', data.type);
  console.log('File size:', data.size, 'bytes');

  // Now save the URL to your product record
  const product = await digibase.from<Product>('products').insert({
    name: 'New Product',
    price: 99.99,
    image_url: data.url, // Save the URL
    stock: 100
  });

  return product.data;
}

// ============================================================================
// EXAMPLE 10: DELETE FILE
// ============================================================================

async function deleteProductImage(imagePath: string) {
  const { data, message } = await digibase.storage.delete(imagePath);

  console.log('File deleted:', message);
  return data;
}

// ============================================================================
// EXAMPLE 11: ERROR HANDLING
// ============================================================================

async function handleErrors() {
  try {
    const { data, error } = await digibase
      .from('mobile_phones')
      .find(999999); // Non-existent ID

    if (error) {
      console.error('API Error:', error);
      return null;
    }

    return data;
  } catch (err) {
    console.error('Network Error:', err);
    return null;
  }
}

// ============================================================================
// EXAMPLE 12: COMPLEX QUERY WITH CHAINING
// ============================================================================

async function complexQuery() {
  const result = await digibase
    .from<MobilePhone>('mobile_phones')
    .select('id,Phone name,model,Year')
    .eq('Year', '2024')
    .sort('Phone name', 'asc')
    .page(1)
    .limit(10)
    .get();

  console.log('Results:', result.data);
  console.log('Total:', result.meta?.total);

  return result;
}

// ============================================================================
// EXPORT ALL EXAMPLES (FOR TESTING)
// ============================================================================

export const examples = {
  getAllPhones,
  getPhoneById,
  getFilteredPhones,
  getPaginatedPhones,
  createPhone,
  bulkCreatePhones,
  updatePhone,
  deletePhone,
  uploadProductImage,
  deleteProductImage,
  handleErrors,
  complexQuery
};

// ============================================================================
// RUN EXAMPLES (UNCOMMENT TO TEST)
// ============================================================================

/*
(async () => {
  console.log('=== Running Digibase SDK Examples ===\n');

  // Example 1: Get all phones
  await getAllPhones();

  // Example 3: Get filtered phones
  await getFilteredPhones();

  // Example 4: Pagination
  await getPaginatedPhones(1);

  console.log('\n=== Examples completed ===');
})();
*/
