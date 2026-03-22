<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('baladiyas', 'noest_name')) {
            Schema::table('baladiyas', function (Blueprint $table) {
                $table->string('noest_name')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('baladiyas', 'noest_name')) {
            Schema::table('baladiyas', function (Blueprint $table) {
                $table->dropColumn('noest_name');
            });
        }
    }
};
