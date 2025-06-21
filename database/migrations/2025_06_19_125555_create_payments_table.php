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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_booking_id');
            $table->foreign('order_booking_id')->references('id')->on('order__bookings')->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->string('payment_method'); // visa/cash/wallet/mada
            $table->decimal('amount', 10, 2);
            $table->string('status'); // pending/paid/failed/refunded
            $table->string('transaction_id')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
