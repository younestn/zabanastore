<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('business_settings')->updateOrInsert(
            ['type' => 'default_seller_commission_type'],
            ['value' => 'percentage', 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('business_settings')->updateOrInsert(
            ['type' => 'default_seller_commission_value'],
            ['value' => '0', 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('business_settings')->updateOrInsert(
            ['type' => 'seller_commission_threshold_amount'],
            ['value' => '5000', 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('business_settings')
            ->whereIn('type', [
                'default_seller_commission_type',
                'default_seller_commission_value',
                'seller_commission_threshold_amount',
            ])
            ->delete();
    }
};
