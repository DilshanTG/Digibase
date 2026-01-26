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
        Schema::create('dynamic_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_model_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('display_name');
            $table->string('type'); // string, text, integer, float, boolean, date, datetime, json, etc.
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_indexed')->default(false);
            $table->boolean('is_searchable')->default(true);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_sortable')->default(true);
            $table->boolean('show_in_list')->default(true);
            $table->boolean('show_in_detail')->default(true);
            $table->string('default_value')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('options')->nullable(); // For enum/select fields
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_fields');
    }
};
