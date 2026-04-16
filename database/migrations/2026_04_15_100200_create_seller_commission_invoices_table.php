<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_commission_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedSmallInteger('invoice_year');
            $table->unsignedTinyInteger('invoice_month');
            $table->date('period_start');
            $table->date('period_end');

            $table->string('commission_type_snapshot', 20)->nullable();
            $table->decimal('commission_value_snapshot', 24, 8)->nullable();

            $table->unsignedInteger('orders_count')->default(0);

            $table->decimal('order_commission_total', 24, 8)->default(0);
            $table->decimal('manual_adjustment_total', 24, 8)->default(0);
            $table->decimal('total_commission', 24, 8)->default(0);

            $table->string('payment_status', 20)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by_admin_id')->nullable();
            $table->text('payment_note')->nullable();

            $table->timestamps();

            $table->unique(
                ['seller_id', 'invoice_year', 'invoice_month'],
                'seller_commission_invoice_unique'
            );

            $table->index(['seller_id', 'payment_status']);
            $table->index(['invoice_year', 'invoice_month']);

            $table->foreign('seller_id')
                ->references('id')
                ->on('sellers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_commission_invoices');
    }
};
