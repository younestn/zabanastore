<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_events')) {
            return;
        }

        Schema::create('shipping_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('order_shipping_detail_id')->nullable()->index();
            $table->string('carrier_key')->nullable()->index();
            $table->string('tracking_number')->nullable()->index();
            $table->string('shipping_status')->nullable()->index();
            $table->string('event_label')->nullable();
            $table->text('event_description')->nullable();
            $table->longText('event_payload')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_events');
    }
};
