<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dynamic_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('table_name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('icon')->default('table');
            $table->boolean('is_active')->default(true);
            $table->boolean('has_timestamps')->default(true);
            $table->boolean('has_soft_deletes')->default(false);
            $table->boolean('generate_api')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_models');
    }
};
