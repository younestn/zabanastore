<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ad_requests')) {
            Schema::table('ad_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('ad_requests', 'completed_purchases_count')) {
                    $table->unsignedBigInteger('completed_purchases_count')->default(0)->after('last_click_at');
                }

                if (!Schema::hasColumn('ad_requests', 'completed_purchases_amount')) {
                    $table->decimal('completed_purchases_amount', 14, 2)->default(0)->after('completed_purchases_count');
                }

                if (!Schema::hasColumn('ad_requests', 'last_purchase_at')) {
                    $table->dateTime('last_purchase_at')->nullable()->after('completed_purchases_amount');
                }
            });
        }

        if (Schema::hasTable('order_details')) {
            Schema::table('order_details', function (Blueprint $table) {
                if (!Schema::hasColumn('order_details', 'ad_request_id')) {
                    $table->unsignedBigInteger('ad_request_id')->nullable()->after('seller_id')->index();
                }

                if (!Schema::hasColumn('order_details', 'ad_attribution_source')) {
                    $table->string('ad_attribution_source', 30)->nullable()->after('ad_request_id');
                }

                if (!Schema::hasColumn('order_details', 'ad_purchase_counted_at')) {
                    $table->dateTime('ad_purchase_counted_at')->nullable()->after('ad_attribution_source');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_details')) {
            Schema::table('order_details', function (Blueprint $table) {
                foreach (['ad_purchase_counted_at', 'ad_attribution_source', 'ad_request_id'] as $column) {
                    if (Schema::hasColumn('order_details', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('ad_requests')) {
            Schema::table('ad_requests', function (Blueprint $table) {
                foreach (['last_purchase_at', 'completed_purchases_amount', 'completed_purchases_count'] as $column) {
                    if (Schema::hasColumn('ad_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
