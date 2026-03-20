<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('notifications', function (Blueprint $table) {
        $table->unsignedBigInteger('user_id')->nullable()->after('sent_to');
        $table->unsignedBigInteger('ad_request_id')->nullable()->after('user_id');
        $table->string('type')->default('info')->after('ad_request_id');
        
        // Add foreign key constraints if needed
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('ad_request_id')->references('id')->on('ad_requests')->onDelete('cascade');
    });
}

public function down()
{
    Schema::table('notifications', function (Blueprint $table) {
        $table->dropForeign(['user_id']);
        $table->dropForeign(['ad_request_id']);
        $table->dropColumn(['user_id', 'ad_request_id', 'type']);
    });
}

};
