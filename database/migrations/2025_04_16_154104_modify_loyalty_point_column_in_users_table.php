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
        // Check if the column exists first
        if (!Schema::hasColumn('users', 'loyalty_point')) {
            // If it doesn't exist, CREATE it
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('loyalty_point', 18, 4)->after('wallet_balance')->default(0)->nullable();
            });
        } else {
            // If it exists, MODIFY it (though this shouldn't happen since it doesn't exist)
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('loyalty_point', 18, 4)->after('wallet_balance')->default(0)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop the column if it exists
        if (Schema::hasColumn('users', 'loyalty_point')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('loyalty_point');
            });
        }
    }
};