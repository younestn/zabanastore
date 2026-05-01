<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ad_pricing_plans')) {
            Schema::create('ad_pricing_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('placement');
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2)->default(0);
                $table->integer('duration_days')->default(1);
                $table->string('currency', 20)->default('DZD');
                $table->boolean('status')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_pricing_plans');
    }
};
