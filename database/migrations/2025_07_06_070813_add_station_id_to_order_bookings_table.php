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
        Schema::table('order__bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('station_id')->nullable();
                $table->foreign('station_id')
                  ->references('id')
                  ->on('stations')
                  ->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order__bookings', function (Blueprint $table) {
            //
        });
    }
};
