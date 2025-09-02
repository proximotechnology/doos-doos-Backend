<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique()->nullable();
            $table->string('country');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('has_license')->default(0);
            $table->string('has_car')->default(0);
            $table->string('password');
            $table->string('montypay_recurring_token')->nullable();
            $table->string('montypay_init_trans_id')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });

        // إدخال يوزر افتراضي بعد إنشاء الجدول

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
