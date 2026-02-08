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
            // Row Level Security (RLS) Rules
            // null = Admin Only (default), 'true' = Public, or expression like 'auth.id == user_id'
            $table->string('list_rule')->nullable()->after('settings')
                ->comment('Who can view the collection. Examples: true, auth.id != null, auth.id == user_id');
            $table->string('view_rule')->nullable()->after('list_rule')
                ->comment('Who can view a single record');
            $table->string('create_rule')->nullable()->after('view_rule')
                ->comment('Who can create new records');
            $table->string('update_rule')->nullable()->after('create_rule')
                ->comment('Who can update records');
            $table->string('delete_rule')->nullable()->after('update_rule')
                ->comment('Who can delete records');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_models', function (Blueprint $table) {
            $table->dropColumn([
                'list_rule',
                'view_rule',
                'create_rule',
                'update_rule',
                'delete_rule',
            ]);
        });
    }
};
