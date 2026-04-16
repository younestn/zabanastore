<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_commission_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('seller_commission_invoice_id')->nullable();

            $table->string('adjustment_type', 20); // add | deduct
            $table->decimal('amount', 24, 8)->default(0);
            $table->text('reason')->nullable();

            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'adjustment_type']);

            $table->foreign('seller_id', 'sca_seller_fk')
    ->references('id')
    ->on('sellers')
    ->cascadeOnDelete();

$table->foreign('seller_commission_invoice_id', 'sca_invoice_fk')
    ->references('id')
    ->on('seller_commission_invoices')
    ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_commission_adjustments');
    }
};
