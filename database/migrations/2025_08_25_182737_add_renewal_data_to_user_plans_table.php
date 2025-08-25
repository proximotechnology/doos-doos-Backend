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
        Schema::table('user__plans', function (Blueprint $table) {
            $table->json('renewal_data')->nullable()->after('remaining_cars');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user__plans', function (Blueprint $table) {
                        $table->json('renewal_data')->nullable()->after('remaining_cars');

        });
    }
};
