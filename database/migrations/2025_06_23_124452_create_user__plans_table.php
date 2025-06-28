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
        Schema::create('user__plans', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id');

            // Status field (assuming it's a string, you can change to enum if needed)
            $table->string('status')->default('active');
            $table->string('remaining_cars')->nullable();
            $table->string('car_limite')->nullable();
            $table->string('date_from')->nullable();
            $table->string('date_end')->nullable();

            $table->decimal('price', 10, 2); // Assuming price is a decimal with 10 digits and 2 decimal places
            $table->string('is_paid')->default(0);

            // Timestamps
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');

            $table->foreign('plan_id')
                  ->references('id')
                  ->on('plans')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user__plans');
    }
};
