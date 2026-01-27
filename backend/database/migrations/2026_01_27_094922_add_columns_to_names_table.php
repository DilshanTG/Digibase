<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('names', function (Blueprint $table) {
            $table->string('test');
            
        });
    }

    public function down(): void
    {
        Schema::table('names', function (Blueprint $table) {
            // Rollback not fully supported for dynamic additions yet
        });
    }
};