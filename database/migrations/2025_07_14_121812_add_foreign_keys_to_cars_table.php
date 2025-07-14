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
         
        $table->unsignedBigInteger('brand_car_id')->nullable();

            // Then add the foreign key constraint
         $table->foreign('brand_car_id')
                  ->references('id')
                  ->on('brand_cars')
                  ->onDelete('set null'); // or 'cascade' if you prefer
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            //
        });
    }
};
