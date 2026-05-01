<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vendor_shipping_companies')) {
            return;
        }

        Schema::table('vendor_shipping_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_shipping_companies', 'carrier_key')) {
                $table->string('carrier_key')->nullable()->after('api_token');
            }
            if (!Schema::hasColumn('vendor_shipping_companies', 'display_name')) {
                $table->string('display_name')->nullable()->after('carrier_key');
            }
            if (!Schema::hasColumn('vendor_shipping_companies', 'credentials')) {
                $table->longText('credentials')->nullable()->after('display_name');
            }
            if (!Schema::hasColumn('vendor_shipping_companies', 'supports_home_delivery')) {
                $table->boolean('supports_home_delivery')->default(false)->after('status');
            }
            if (!Schema::hasColumn('vendor_shipping_companies', 'supports_desk_delivery')) {
                $table->boolean('supports_desk_delivery')->default(false)->after('supports_home_delivery');
            }
            if (!Schema::hasColumn('vendor_shipping_companies', 'last_tested_at')) {
                $table->timestamp('last_tested_at')->nullable()->after('connected_since');
            }
            if (!Schema::hasColumn('vendor_shipping_companies', 'last_error')) {
                $table->text('last_error')->nullable()->after('last_tested_at');
            }
            if (!Schema::hasColumn('vendor_shipping_companies', 'is_connected')) {
                $table->boolean('is_connected')->default(false)->after('last_error');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vendor_shipping_companies')) {
            return;
        }

        Schema::table('vendor_shipping_companies', function (Blueprint $table) {
            foreach ([
                'carrier_key',
                'display_name',
                'credentials',
                'supports_home_delivery',
                'supports_desk_delivery',
                'last_tested_at',
                'last_error',
                'is_connected',
            ] as $column) {
                if (Schema::hasColumn('vendor_shipping_companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
