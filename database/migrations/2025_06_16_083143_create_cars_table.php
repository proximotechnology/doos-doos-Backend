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
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->string('make');
            $table->unsignedBigInteger('owner_id');
            $table->string('model');
            $table->integer('year');
            $table->text('description');
            $table->string('address');

            $table->string('vin');
            $table->string('number');
            $table->string('status')->default('pending');
            $table->decimal('price', 10, 2);
            $table->decimal('lat', 10, 7); // خط الطول (يبدو أن هناك خطأ في النموذج بتكرار 'lang')
            $table->decimal('lang', 10, 7); // خط العرض

            $table->timestamps();

            // Foreign key constraint
            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
