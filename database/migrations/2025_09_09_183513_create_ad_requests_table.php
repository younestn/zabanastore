<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
           Schema::create("ad_requests", function (Blueprint $table) {
        $table->id();
        $table->foreignId("vendor_id")->constrained("sellers")->onDelete("cascade");
        $table->foreignId("product_id")->constrained("products")->onDelete("cascade");
        $table->string("ad_type"); // banner, sidebar, product, popup, email
        $table->integer("duration_days");
        $table->decimal("price", 10, 2);
        $table->string("image_path")->nullable();
        $table->text("notes")->nullable();
        $table->string("status")->default("pending"); // pending, approved, rejected
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists("ad_requests");
    }
};
