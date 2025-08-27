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
        Schema::table('cars', function (Blueprint $table) {

            $table->foreignId('user_plan_id')
                  ->nullable()
                  ->constrained('user__plans')
                  ->cascadeOnDelete();
                        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
                     // حذف المفتاح الخارجي أولاً
            $table->dropForeign(['user_plan_id']);

            // ثم حذف الحقل
            $table->dropColumn('user_plan_id');
        });
    }
};
