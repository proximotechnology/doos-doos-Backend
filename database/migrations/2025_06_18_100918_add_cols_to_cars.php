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

            $table->integer('is_rented')->default(0);
            
            $table->string('image_license')->nullable();
            $table->string('number_license')->nullable();
            $table->string('state')->nullable();
            $table->text('description_condition')->nullable();
            $table->text('advanced_notice')->nullable();
            $table->integer('min_day_trip')->nullable();
            $table->integer('max_day_trip')->nullable();
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
