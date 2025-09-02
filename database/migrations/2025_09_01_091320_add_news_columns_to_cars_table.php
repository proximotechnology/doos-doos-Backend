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
        Schema::table('cars', function (Blueprint $table) {
            $table->unsignedBigInteger('car_model_id')->nullable()->after('owner_id');

            // Then add the foreign key constraint
            $table->foreign('car_model_id')
                  ->references('id')
                  ->on('car_models')
                  ->onDelete('set null'); // or 'cascade' if you prefer


            $table->unsignedBigInteger('brand_id')->nullable();

                // Then add the foreign key constraint
            $table->foreign('brand_id')
                    ->references('id')
                    ->on('brands')
                    ->onDelete('set null'); // or 'cascade' if you prefer


            $table->string('extenal_image')->nullable();

            $table->unsignedBigInteger('model_year_id')->nullable();

                // Then add the foreign key constraint
            $table->foreign('model_year_id')
                    ->references('id')
                    ->on('model_years')
                    ->onDelete('set null'); // or 'cascade' if you prefer
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropForeign(['car_model_id']);
            $table->dropForeign(['brand_id']);
            $table->dropForeign(['model_year_id']);
        });
    }
};
