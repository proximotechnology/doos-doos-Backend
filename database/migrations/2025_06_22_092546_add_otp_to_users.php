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
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp')->nullable();
            $table->string('type')->default(0);

        });


        DB::table('users')->insert([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'phone' => '01000000000',
            'type' => '1',

            'country' => 'Egypt',
            'password' => Hash::make('12345678'),
            'created_at' => now(),
            'updated_at' => now(),
            'email_verified_at' => now(),
        ]);


        DB::table('users')->insert([
            'name' => 'User',
            'email' => 'user@example.com',
            'phone' => '01900000000',
            'type' => '0',
            'country' => 'Egypt',
            'password' => Hash::make('12345678'),
            'created_at' => now(),
            'updated_at' => now(),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('otp');
            $table->dropColumn('type');

        });
    }
};
