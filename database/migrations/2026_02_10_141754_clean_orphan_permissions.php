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
        // Helper to allow running raw SQL
        \Illuminate\Support\Facades\DB::statement("DELETE FROM permissions WHERE name LIKE '%storage_file%'");
        \Illuminate\Support\Facades\DB::statement("DELETE FROM permissions WHERE name LIKE '%file_system_item%'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
