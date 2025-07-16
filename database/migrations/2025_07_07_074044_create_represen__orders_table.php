<?php

use App\Models\Order_Booking;
use App\Models\Representative;
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
        Schema::create('represen__orders', function (Blueprint $table) {
            $table->id();

            $table->string('status');
            $table->timestamps();

            // Foreign key constraints
            $table->foreignIdFor(Order_Booking::class)->constrained()->cascadeOnDelete();

            $table->foreignIdFor(Representative::class)->constrained()->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('represen__orders');
    }
};
