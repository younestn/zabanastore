<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('seller_badges')) {
            Schema::create('seller_badges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seller_id')->unique();
                $table->string('badge_key')->nullable();
                $table->string('auto_badge_key')->nullable();
                $table->string('manual_badge_key')->nullable();
                $table->boolean('manual_override')->default(false);
                $table->text('manual_override_reason')->nullable();
                $table->decimal('compliance_score', 5, 2)->default(0);
                $table->unsignedTinyInteger('badge_level')->nullable();
                $table->timestamp('recalculated_at')->nullable();
                $table->timestamps();

                $table->index(['badge_key', 'manual_override']);
            });
        }

        if (!Schema::hasTable('seller_badge_histories')) {
            Schema::create('seller_badge_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('seller_id')->index();
                $table->string('old_badge_key')->nullable();
                $table->string('new_badge_key')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->string('change_type')->default('automatic');
                $table->text('reason')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['seller_id', 'change_type']);
            });
        }

        if (Schema::hasTable('business_settings')) {
            DB::table('business_settings')->updateOrInsert(
                ['type' => 'seller_badge_settings'],
                [
                    'value' => json_encode(config('seller_badges'), JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_badge_histories');
        Schema::dropIfExists('seller_badges');

        if (Schema::hasTable('business_settings')) {
            DB::table('business_settings')->where('type', 'seller_badge_settings')->delete();
        }
    }
};
