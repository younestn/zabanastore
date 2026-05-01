<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_shipping_details')) {
            return;
        }

        Schema::table('order_shipping_details', function (Blueprint $table) {
            if (!Schema::hasColumn('order_shipping_details', 'remote_order_id')) {
                $table->string('remote_order_id')->nullable()->after('tracking_id');
            }
            if (!Schema::hasColumn('order_shipping_details', 'remote_display_id')) {
                $table->string('remote_display_id')->nullable()->after('remote_order_id');
            }
            if (!Schema::hasColumn('order_shipping_details', 'delivery_price')) {
                $table->decimal('delivery_price', 12, 2)->nullable()->after('remote_display_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('order_shipping_details')) {
            return;
        }

        Schema::table('order_shipping_details', function (Blueprint $table) {
            foreach (['remote_order_id', 'remote_display_id', 'delivery_price'] as $column) {
                if (Schema::hasColumn('order_shipping_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
