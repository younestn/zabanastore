<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_shipping_details')) {
            return;
        }

        Schema::create('order_shipping_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('carrier_key')->nullable()->index();
            $table->string('carrier_name')->nullable();
            $table->string('delivery_service_name')->nullable();
            $table->string('service_name')->nullable();
            $table->string('delivery_type')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_id')->nullable();
            $table->string('tracking')->nullable();
            $table->string('shipping_status')->nullable();
            $table->string('status')->nullable();
            $table->longText('shipment_payload')->nullable();
            $table->longText('request_payload')->nullable();
            $table->longText('shipment_response')->nullable();
            $table->longText('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->string('desk_code')->nullable();
            $table->string('desk_name')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_shipping_details');
    }
};
