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
        Schema::create('cars__features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cars_id');
            $table->string('mileage_range');
            $table->string('transmission');
            $table->string('mechanical_condition');
            $table->boolean('all_have_seatbelts')->default(false);
            $table->integer('num_of_door');
            $table->integer('num_of_seat');
            $table->text('additional_features')->nullable(); // Storing array as JSON

            $table->foreign('cars_id')->references('id')->on('cars')->onDelete('cascade');
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars__features');
    }
};
