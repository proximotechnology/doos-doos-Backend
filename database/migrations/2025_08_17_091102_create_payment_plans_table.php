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
        Schema::create('payment_plans', function (Blueprint $table) {
            $table->id();
          $table->foreignId('user_plan_id')->constrained('user__plans')->cascadeOnDelete();
            $table->string('gateway')->default('montypay');
            $table->string('transaction_id')->nullable();
            $table->string('recurring_token')->nullable();
            $table->string('status')->default('pending'); // pending, paid, failed
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_plans');
    }
};
