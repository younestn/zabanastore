<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_requests')) {
            return;
        }

        Schema::table('ad_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('ad_requests', 'ad_pricing_plan_id')) {
                $table->unsignedBigInteger('ad_pricing_plan_id')->nullable()->after('product_id');
            }

            if (!Schema::hasColumn('ad_requests', 'plan_name')) {
                $table->string('plan_name')->nullable()->after('ad_pricing_plan_id');
            }

            if (!Schema::hasColumn('ad_requests', 'plan_price')) {
                $table->decimal('plan_price', 12, 2)->nullable()->after('plan_name');
            }

            if (!Schema::hasColumn('ad_requests', 'plan_duration_days')) {
                $table->integer('plan_duration_days')->nullable()->after('plan_price');
            }

            if (!Schema::hasColumn('ad_requests', 'plan_currency')) {
                $table->string('plan_currency', 20)->nullable()->after('plan_duration_days');
            }

            if (!Schema::hasColumn('ad_requests', 'impressions_web')) {
                $table->unsignedBigInteger('impressions_web')->default(0)->after('is_paid');
            }

            if (!Schema::hasColumn('ad_requests', 'impressions_app')) {
                $table->unsignedBigInteger('impressions_app')->default(0)->after('impressions_web');
            }

            if (!Schema::hasColumn('ad_requests', 'clicks_web')) {
                $table->unsignedBigInteger('clicks_web')->default(0)->after('impressions_app');
            }

            if (!Schema::hasColumn('ad_requests', 'clicks_app')) {
                $table->unsignedBigInteger('clicks_app')->default(0)->after('clicks_web');
            }

            if (!Schema::hasColumn('ad_requests', 'last_impression_at')) {
                $table->dateTime('last_impression_at')->nullable()->after('clicks_app');
            }

            if (!Schema::hasColumn('ad_requests', 'last_click_at')) {
                $table->dateTime('last_click_at')->nullable()->after('last_impression_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_requests')) {
            return;
        }

        Schema::table('ad_requests', function (Blueprint $table) {
            foreach ([
                'last_click_at',
                'last_impression_at',
                'clicks_app',
                'clicks_web',
                'impressions_app',
                'impressions_web',
                'plan_currency',
                'plan_duration_days',
                'plan_price',
                'plan_name',
                'ad_pricing_plan_id',
            ] as $column) {
                if (Schema::hasColumn('ad_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
