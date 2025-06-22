<?php

namespace App\Services\registartion;

use App\Models\User;
use App\Models\Driver;
use App\Models\Provider_Product; // Import your ProviderProduct model
use App\Models\Provider_Service; // Import your ProviderService model
use App\Models\FoodType_ProductProvider; // Import your ProviderService model

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache; // Import Cache facade
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class register
{
    /**
     * Register a new user and create related records based on user type.
     *
     * @param array $data
     * @return User
     */
public function register(array $data): User
{
    DB::beginTransaction();

    try {
        // تحقق من وجود البريد الإلكتروني أو رقم الهاتف
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'], // الهاتف مطلوب الآن
                'country' => $data['country'], // إضافة البلد
                'password' => Hash::make($data['password']),
            ];

        $user = User::create($userData);

        DB::commit();

        return $user;

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}

    public function verifyOtp(string $otp, User $user): bool
    {
        // Retrieve the OTP data from the cache using the authenticated user's ID
        $otpData = Cache::get('otp_' . $user->id);

        // Check if the OTP data exists in the cache
        if (!$otpData) {
            throw new \Exception('No OTP data found in cache.');
        }

        // Retrieve the OTP from the cache data
        $sessionOtp = $otpData['otp'];

        // Check if the OTP matches
        if ($otp !== $sessionOtp) {
            throw new \Exception('Invalid OTP.');
        }

        // If OTP is valid, update the user's otp_verified column
        $user->otp = 1; // Assuming the column name is otp_verified
        $user->save(); // Save the changes to the database

        // Clear the OTP data from the cache after successful verification
        Cache::forget('otp_' . $user->id);

        return true; // Return true if OTP verification is successful
    }
}
