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
        Schema::table('dynamic_fields', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('is_searchable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_fields', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};
