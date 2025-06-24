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
        Schema::create('user__notifies', function (Blueprint $table) {
            $table->id();
            $table->text('notify'); // نص الإشعار
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // يمكن أن يكون null
            $table->string('is_read')->default('pending'); // حالة القراءة - افتراضي pending
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user__notifies');
    }
};
