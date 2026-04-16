<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->string('seller_commission_type', 20)
                ->default('default')
                ->after('sales_commission_percentage');

            $table->decimal('seller_commission_value', 24, 8)
                ->nullable()
                ->after('seller_commission_type');
        });

        DB::table('sellers')
            ->whereNotNull('sales_commission_percentage')
            ->update([
                'seller_commission_type' => 'percentage',
                'seller_commission_value' => DB::raw('sales_commission_percentage'),
            ]);

        DB::table('sellers')
            ->whereNull('sales_commission_percentage')
            ->update([
                'seller_commission_type' => 'default',
                'seller_commission_value' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('sellers', function (Blueprint $table) {
            $table->dropColumn([
                'seller_commission_type',
                'seller_commission_value',
            ]);
        });
    }
};
