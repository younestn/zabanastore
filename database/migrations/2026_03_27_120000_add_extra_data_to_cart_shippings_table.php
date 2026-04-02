<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cart_shippings', 'extra_data')) {
            Schema::table('cart_shippings', function (Blueprint $table) {
                $table->longText('extra_data')->nullable()->after('shipping_cost');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cart_shippings', 'extra_data')) {
            Schema::table('cart_shippings', function (Blueprint $table) {
                $table->dropColumn('extra_data');
            });
        }
    }
};
