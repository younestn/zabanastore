<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vendor_shipping_companies') &&
            !Schema::hasColumn('vendor_shipping_companies', 'noest_guid')) {
            Schema::table('vendor_shipping_companies', function (Blueprint $table) {
                $table->string('noest_guid', 255)->nullable()->after('website');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vendor_shipping_companies') &&
            Schema::hasColumn('vendor_shipping_companies', 'noest_guid')) {
            Schema::table('vendor_shipping_companies', function (Blueprint $table) {
                $table->dropColumn('noest_guid');
            });
        }
    }
};
