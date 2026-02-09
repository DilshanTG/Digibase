<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\DynamicModel;
use App\Models\DynamicField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Core API Engine Test Suite
 * 
 * Tests the unified CoreDataController with:
 * - 🛡️ Iron Dome (API key validation)
 * - 🩺 Schema Doctor (Dynamic validation)
 * - ⚡ Turbo Cache (Caching)
 * - 📡 Live Wire (Real-time events)
 * - 🔒 Transaction Wrapper (Atomic operations)
 * - 🎯 Type-Safe Casting (Strict types)
 * - 🚦 Rate Limiting (Dynamic per-key)
 */
class CoreApiEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected ApiKey $apiKey;
    protected DynamicModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create API key with full permissions
        $this->apiKey = ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'Test Key',
            'key' => 'sk_test_key_12345678901234567890',
            'type' => 'secret',
            'scopes' => ['read', 'write', 'delete'],
            'allowed_tables' => null, // Access to all tables
            'rate_limit' => 100,
            'is_active' => true,
        ]);

        // Create a test dynamic model
        $this->model = DynamicModel::create([
            'name' => 'test_products',
            'display_name' => 'Test Products',
            'table_name' => 'test_products',
            'description' => 'Test product model',
            'is_active' => true,
            'generate_api' => true,
            'has_timestamps' => true,
            'has_soft_deletes' => false,
            'list_rule' => 'true',
            'view_rule' => 'true',
            'create_rule' => 'true',
            'update_rule' => 'true',
            'delete_rule' => 'true',
        ]);

        // Create fields
        DynamicField::create([
            'dynamic_model_id' => $this->model->id,
            'name' => 'name',
            'display_name' => 'Product Name',
            'type' => 'string',
            'is_required' => true,
            'is_unique' => false,
            'is_searchable' => true,
            'is_filterable' => true,
            'is_sortable' => true,
        ]);

        DynamicField::create([
            'dynamic_model_id' => $this->model->id,
            'name' => 'price',
            'display_name' => 'Price',
            'type' => 'float',
            'is_required' => true,
            'is_unique' => false,
            'is_searchable' => false,
            'is_filterable' => true,
            'is_sortable' => true,
        ]);

        DynamicField::create([
            'dynamic_model_id' => $this->model->id,
            'name' => 'quantity',
            'display_name' => 'Quantity',
            'type' => 'integer',
            'is_required' => false,
            'is_unique' => false,
            'is_searchable' => false,
            'is_filterable' => false,
            'is_sortable' => false,
            'default_value' => 0,
        ]);

        DynamicField::create([
            'dynamic_model_id' => $this->model->id,
            'name' => 'is_active',
            'display_name' => 'Active',
            'type' => 'boolean',
            'is_required' => false,
            'is_unique' => false,
            'default_value' => true,
        ]);

        // Create the actual table
        Schema::create('test_products', function ($table) {
            $table->id();
            $table->string('name');
            $table->float('price');
            $table->integer('quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_products');
        parent::tearDown();
    }

    /** @test */
    public function it_requires_api_key()
    {
        $response = $this->getJson('/api/v1/data/test_products');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'MISSING_API_KEY',
            ]);
    }

    /** @test */
    public function it_validates_api_key()
    {
        $response = $this->getJson('/api/v1/data/test_products', [
            'Authorization' => 'Bearer invalid_key',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'INVALID_API_KEY',
            ]);
    }

    /** @test */
    public function it_lists_records_with_v1_endpoint()
    {
        // Create test records
        DB::table('test_products')->insert([
            ['name' => 'Product 1', 'price' => 10.99, 'quantity' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Product 2', 'price' => 20.99, 'quantity' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson('/api/v1/data/test_products', [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'price', 'quantity', 'is_active'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(2, 'data');
    }

    /** @test */
    public function it_creates_record_with_type_safe_casting()
    {
        $response = $this->postJson('/api/v1/data/test_products', [
            'name' => 'New Product',
            'price' => '29.99', // String should be cast to float
            'quantity' => '15', // String should be cast to integer
            'is_active' => '1', // String should be cast to boolean
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'price', 'quantity', 'is_active']]);

        // Verify types in database
        $record = DB::table('test_products')->where('name', 'New Product')->first();
        $this->assertIsFloat($record->price);
        $this->assertIsInt($record->quantity);
        $this->assertEquals(1, $record->is_active);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->postJson('/api/v1/data/test_products', [
            'quantity' => 10,
            // Missing required 'name' and 'price'
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors' => ['name', 'price'],
            ]);
    }

    /** @test */
    public function it_updates_record_with_transaction_safety()
    {
        // Create a record
        $id = DB::table('test_products')->insertGetId([
            'name' => 'Original Product',
            'price' => 10.99,
            'quantity' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/data/test_products/{$id}", [
            'name' => 'Updated Product',
            'price' => 15.99,
        ], [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $id,
                    'name' => 'Updated Product',
                    'price' => 15.99,
                ],
            ]);
    }

    /** @test */
    public function it_deletes_record()
    {
        $id = DB::table('test_products')->insertGetId([
            'name' => 'To Delete',
            'price' => 10.99,
            'quantity' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/data/test_products/{$id}", [], [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Record deleted successfully']);

        $this->assertDatabaseMissing('test_products', ['id' => $id]);
    }

    /** @test */
    public function it_returns_schema_information()
    {
        $response = $this->getJson('/api/v1/data/test_products/schema', [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'model' => ['name', 'display_name', 'table_name'],
                'fields' => [
                    '*' => ['name', 'type', 'is_required', 'is_unique'],
                ],
                'endpoints' => ['list', 'create', 'show', 'update', 'delete'],
            ]);
    }

    /** @test */
    public function it_enforces_rate_limiting()
    {
        // Update API key to have very low rate limit
        $this->apiKey->update(['rate_limit' => 2]);

        // Make 3 requests (should hit limit on 3rd)
        $this->getJson('/api/v1/data/test_products', [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ])->assertStatus(200);

        $this->getJson('/api/v1/data/test_products', [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ])->assertStatus(200);

        $response = $this->getJson('/api/v1/data/test_products', [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ]);

        $response->assertStatus(429)
            ->assertJsonStructure([
                'message',
                'retry_after',
                'limit',
                'remaining',
            ]);
    }

    /** @test */
    public function it_includes_rate_limit_headers()
    {
        $response = $this->getJson('/api/v1/data/test_products', [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-RateLimit-Limit')
            ->assertHeader('X-RateLimit-Remaining')
            ->assertHeader('X-RateLimit-Reset');
    }

    /** @test */
    public function it_maintains_backward_compatibility_with_legacy_endpoint()
    {
        DB::table('test_products')->insert([
            'name' => 'Legacy Test',
            'price' => 10.99,
            'quantity' => 5,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Test legacy endpoint (without v1 prefix)
        $response = $this->getJson('/api/data/test_products', [
            'Authorization' => 'Bearer ' . $this->apiKey->key,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'price'],
                ],
                'meta',
            ]);
    }

    /** @test */
    public function it_enforces_scope_permissions()
    {
        // Create read-only API key
        $readOnlyKey = ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'Read Only Key',
            'key' => 'pk_readonly_12345678901234567890',
            'type' => 'public',
            'scopes' => ['read'], // Only read permission
            'is_active' => true,
        ]);

        // GET should work
        $response = $this->getJson('/api/v1/data/test_products', [
            'Authorization' => 'Bearer ' . $readOnlyKey->key,
        ]);
        $response->assertStatus(200);

        // POST should fail
        $response = $this->postJson('/api/v1/data/test_products', [
            'name' => 'Test',
            'price' => 10.99,
        ], [
            'Authorization' => 'Bearer ' . $readOnlyKey->key,
        ]);
        $response->assertStatus(403)
            ->assertJson([
                'error_code' => 'METHOD_NOT_ALLOWED',
            ]);
    }

    /** @test */
    public function it_enforces_table_level_access()
    {
        // Create API key with limited table access
        $limitedKey = ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'Limited Key',
            'key' => 'sk_limited_12345678901234567890',
            'type' => 'secret',
            'scopes' => ['read', 'write', 'delete'],
            'allowed_tables' => ['other_table'], // Not test_products
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/data/test_products', [
            'Authorization' => 'Bearer ' . $limitedKey->key,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error_code' => 'TABLE_ACCESS_DENIED',
            ]);
    }
}
