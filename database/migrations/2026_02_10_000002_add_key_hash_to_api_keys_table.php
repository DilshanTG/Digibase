<?php

use App\Models\ApiKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_hash', 64)->nullable()->after('key')->index();
        });

        // Backfill existing keys with their SHA-256 hash
        foreach (ApiKey::all() as $apiKey) {
            $apiKey->update(['key_hash' => hash('sha256', $apiKey->key)]);
        }
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('key_hash');
        });
    }
};
