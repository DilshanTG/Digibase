<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SKIPPING: Causing test failures due to duplicate column
        /*
        Schema::table('names', function (Blueprint $table) {
            $table->string('test');
        });
        */
    }

    public function down(): void
    {
    }
};