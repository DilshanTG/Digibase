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
        Schema::create('dynamic_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_model_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_model_id')->constrained('dynamic_models')->onDelete('cascade');
            $table->string('name');
            $table->string('type'); // hasOne, hasMany, belongsTo, belongsToMany
            $table->string('foreign_key')->nullable();
            $table->string('local_key')->nullable();
            $table->string('pivot_table')->nullable(); // For many-to-many
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_relationships');
    }
};
