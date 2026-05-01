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
            if (!Schema::hasColumn('ad_requests', 'shop_id')) {
                $table->unsignedBigInteger('shop_id')->nullable()->after('vendor_id');
            }
            if (!Schema::hasColumn('ad_requests', 'title')) {
                $table->string('title')->nullable()->after('product_id');
            }
            if (!Schema::hasColumn('ad_requests', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (!Schema::hasColumn('ad_requests', 'placement')) {
                $table->string('placement')->nullable()->default('home_top')->after('ad_type');
            }
            if (!Schema::hasColumn('ad_requests', 'redirect_type')) {
                $table->string('redirect_type')->nullable()->after('placement');
            }
            if (!Schema::hasColumn('ad_requests', 'redirect_id')) {
                $table->unsignedBigInteger('redirect_id')->nullable()->after('redirect_type');
            }
            if (!Schema::hasColumn('ad_requests', 'redirect_url')) {
                $table->string('redirect_url')->nullable()->after('redirect_id');
            }
            if (!Schema::hasColumn('ad_requests', 'payment_receipt')) {
                $table->string('payment_receipt')->nullable()->after('image_path');
            }
            if (!Schema::hasColumn('ad_requests', 'payment_receipt_storage_type')) {
                $table->string('payment_receipt_storage_type', 20)->nullable()->default('public')->after('payment_receipt');
            }
            if (!Schema::hasColumn('ad_requests', 'payment_status')) {
                $table->string('payment_status')->nullable()->default('pending')->after('payment_receipt_storage_type');
            }
            if (!Schema::hasColumn('ad_requests', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('ad_requests', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('ad_requests', 'start_date')) {
                $table->dateTime('start_date')->nullable()->after('admin_note');
            }
            if (!Schema::hasColumn('ad_requests', 'end_date')) {
                $table->dateTime('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('ad_requests', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('ad_requests', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('ad_requests', 'rejected_at')) {
                $table->dateTime('rejected_at')->nullable()->after('approved_by');
            }
            if (!Schema::hasColumn('ad_requests', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
            }
            if (!Schema::hasColumn('ad_requests', 'priority')) {
                $table->integer('priority')->nullable()->default(0)->after('rejected_by');
            }
            if (!Schema::hasColumn('ad_requests', 'is_paid')) {
                $table->boolean('is_paid')->default(false)->after('priority');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ad_requests')) {
            return;
        }

        Schema::table('ad_requests', function (Blueprint $table) {
            $columns = [
                'shop_id',
                'title',
                'description',
                'placement',
                'redirect_type',
                'redirect_id',
                'redirect_url',
                'payment_receipt',
                'payment_receipt_storage_type',
                'payment_status',
                'rejection_reason',
                'admin_note',
                'start_date',
                'end_date',
                'approved_at',
                'approved_by',
                'rejected_at',
                'rejected_by',
                'priority',
                'is_paid',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('ad_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
