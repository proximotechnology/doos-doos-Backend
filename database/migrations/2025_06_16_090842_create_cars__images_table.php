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
        Schema::create('cars__images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cars_id');
            $table->string('image'); // Assuming this stores the image path or filename
            $table->foreign('cars_id')
                  ->references('id')
                  ->on('cars')
                  ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars__images');
    }
};
