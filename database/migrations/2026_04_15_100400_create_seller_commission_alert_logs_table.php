<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_commission_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');

            $table->decimal('threshold_amount', 24, 8)->default(0);
            $table->decimal('unpaid_amount', 24, 8)->default(0);

            $table->string('recipient_type', 20);   // seller | admin
            $table->string('alert_status', 20)->default('sent');

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['seller_id', 'recipient_type']);
            $table->index(['alert_status', 'sent_at']);

            $table->foreign('seller_id')
                ->references('id')
                ->on('sellers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_commission_alert_logs');
    }
};
