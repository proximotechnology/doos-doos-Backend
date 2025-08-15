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
        Schema::create('order__bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('car_id')->constrained()->onDelete('cascade');

            $table->string('date_from');
            $table->string('date_end');
            $table->string('completed_at')->nullable();

            $table->string('is_paid')->default(0);
            $table->string('with_driver');
            $table->string('payment_method');

            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lang', 10, 7)->nullable();
            $table->decimal('total_price', 10, 2);
            $table->string('status')->default('pending');
            $table->string('repres_status')->default('0');

            $table->string('driver_type');
            $table->string('has_representative')->default(0);

            $table->timestamps();


            // Optional index for better performance on date queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order__bookings');
    }
};
