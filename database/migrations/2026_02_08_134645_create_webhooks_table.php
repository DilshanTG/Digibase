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
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_model_id')->constrained('dynamic_models')->onDelete('cascade');
            $table->string('name')->nullable(); // Optional friendly name
            $table->string('url'); // Target webhook URL
            $table->string('secret')->nullable(); // For HMAC signature verification
            $table->json('events')->default('["created","updated","deleted"]'); // Events to trigger
            $table->json('headers')->nullable(); // Custom headers to send
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('failure_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};
