<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\DynamicModel;
use App\Models\DynamicRelationship;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class DynamicRelationTest extends TestCase
{
    // NO RefreshDatabase - Manual Schema Management
    
    protected $user;
    protected $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->createKeyTables();

        // Create a user for ownership checks
        $this->user = User::factory()->create();

        // Dynamically create physical tables for the test
        Schema::create('test_authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('test_books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('author_id'); // No constraint needed for test logic, just column
            $table->timestamps();
        });

        // Create API Key for testing
        $this->apiKey = ApiKey::create([
            'user_id' => $this->user->id,
            'name' => 'Test Key',
            'key' => 'test_key_' . uniqid(),
            'type' => 'secret',
            'scopes' => ['read', 'write', 'delete'],
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_books');
        Schema::dropIfExists('test_authors');
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('dynamic_relationships');
        Schema::dropIfExists('dynamic_fields');
        Schema::dropIfExists('dynamic_models');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    protected function createKeyTables()
    {
        // 1. Users
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // 2. Dynamic Models
        Schema::create('dynamic_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->string('name');
            $table->string('table_name');
            $table->string('display_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('generate_api')->default(true);
            $table->boolean('has_soft_deletes')->default(false);
            $table->text('list_rule')->nullable();
            $table->text('view_rule')->nullable();
            $table->text('create_rule')->nullable();
            $table->text('settings')->nullable();
            $table->timestamps();
        });

        // 3. Dynamic Fields (needed for validation/logic)
        Schema::create('dynamic_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_model_id');
            $table->string('name');
            $table->string('type')->default('string');
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_searchable')->default(false);
            $table->boolean('is_sortable')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });

        // 4. Dynamic Relationships
        Schema::create('dynamic_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_model_id');
            $table->foreignId('related_model_id')->nullable();
            $table->string('name')->nullable(); // Should be method_name?
            $table->string('type');
            $table->string('foreign_key')->nullable();
            $table->string('local_key')->nullable();
            $table->string('method_name')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // 5. API Keys
        if (!Schema::hasTable('api_keys')) {
            Schema::create('api_keys', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id');
                $table->string('name');
                $table->string('key', 64)->unique();
                $table->string('key_hash', 64)->nullable()->index();
                $table->string('type')->default('public');
                $table->json('scopes')->nullable();
                $table->integer('rate_limit')->default(60);
                $table->boolean('is_active')->default(true);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
            });
        }

        // 6. Personal Access Tokens (Sanctum) - Needed for auth checks
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /** @test */
    public function it_can_fetch_has_many_relationship()
    {
        // 1. Registers Models
        $authorModel = DynamicModel::create([
            'user_id' => $this->user->id,
            'name' => 'Author',
            'table_name' => 'test_authors',
            'display_name' => 'Authors', 
            'is_active' => true,
            'generate_api' => true,
            'list_rule' => 'true', // Public Access
            'view_rule' => 'true',
        ]);

        $bookModel = DynamicModel::create([
            'user_id' => $this->user->id,
            'name' => 'Book',
            'table_name' => 'test_books',
            'display_name' => 'Books',
            'is_active' => true,
            'generate_api' => true,
            'list_rule' => 'true',
            'view_rule' => 'true',
        ]);

        // 2. Define Relationship: Author hasMany Books
        DynamicRelationship::create([
            'dynamic_model_id' => $authorModel->id,
            'related_model_id' => $bookModel->id,
            'type' => 'hasMany',
            'name' => 'books',        // used in ?include=books
            'method_name' => 'books', 
            'foreign_key' => 'author_id',
        ]);

        // 3. Seed Data
        $authorId = DB::table('test_authors')->insertGetId([
            'name' => 'J.K. Rowling',
            'created_at' => now(), 'updated_at' => now()
        ]);

        DB::table('test_books')->insert([
            ['title' => 'Harry Potter 1', 'author_id' => $authorId, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Harry Potter 2', 'author_id' => $authorId, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 4. Hit API (Protected Route /api/data/...)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey->key)
            ->getJson("/api/data/test_authors?include=books");

        // 5. Assertions
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'J.K. Rowling')
            ->assertJsonCount(2, 'data.0.books') // Verify relationship loaded
            ->assertJsonPath('data.0.books.0.title', 'Harry Potter 1');
    }

    /** @test */
    public function it_can_fetch_belongs_to_relationship()
    {
        // 1. Register Models
        $authorModel = DynamicModel::create([
            'user_id' => $this->user->id,
            'name' => 'Author',
            'table_name' => 'test_authors',
            'is_active' => true,
            'generate_api' => true,
            'list_rule' => 'true',
        ]);

        $bookModel = DynamicModel::create([
            'user_id' => $this->user->id,
            'name' => 'Book',
            'table_name' => 'test_books',
            'is_active' => true,
            'generate_api' => true,
            'list_rule' => 'true',
        ]);

        // 2. Define Relationship: Book belongsTo Author
        DynamicRelationship::create([
            'dynamic_model_id' => $bookModel->id,
            'related_model_id' => $authorModel->id,
            'type' => 'belongsTo',
            'name' => 'author',       // used in ?include=author
            'method_name' => 'author',
            'foreign_key' => 'author_id',
        ]);

        // 3. Seed Data
        $authorId = DB::table('test_authors')->insertGetId([
            'name' => 'George R.R. Martin',
            'created_at' => now(), 'updated_at' => now()
        ]);

        DB::table('test_books')->insert([
            'title' => 'Game of Thrones',
            'author_id' => $authorId,
            'created_at' => now(), 'updated_at' => now()
        ]);

        // 4. Hit API (Protected Route)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->apiKey->key)
            ->getJson("/api/data/test_books?include=author");

        // 5. Assertions
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Game of Thrones')
            ->assertJsonPath('data.0.author.name', 'George R.R. Martin'); // Verify relationship loaded
    }
}
