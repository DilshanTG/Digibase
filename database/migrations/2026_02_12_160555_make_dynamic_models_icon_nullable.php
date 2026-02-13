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
        Schema::table('dynamic_models', function (Blueprint $table) {
            $table->string('icon')->nullable()->default('heroicon-o-table-cells')->change();
            $table->text('description')->nullable()->change();
            $table->text('settings')->nullable()->change();
            $table->string('list_rule')->nullable()->change();
            $table->string('view_rule')->nullable()->change();
            $table->string('create_rule')->nullable()->change();
            $table->string('update_rule')->nullable()->change();
            $table->string('delete_rule')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_models', function (Blueprint $table) {
            $table->string('icon')->nullable(false)->default(null)->change();
            $table->text('description')->nullable(false)->change();
            $table->text('settings')->nullable(false)->change();
            $table->string('list_rule')->nullable(false)->change();
            $table->string('view_rule')->nullable(false)->change();
            $table->string('create_rule')->nullable(false)->change();
            $table->string('update_rule')->nullable(false)->change();
            $table->string('delete_rule')->nullable(false)->change();
        });
    }
};
