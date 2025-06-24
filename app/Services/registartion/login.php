<?php

namespace App\Services\registartion;

use App\Models\User;
use App\Models\Driver;
use App\Models\Provider_Product; // Import your ProviderProduct model
use App\Models\Provider_Service; // Import your ProviderService model
use Illuminate\Support\Facades\Hash;

class login
{
    /**
     * Register a new user and create related records based on user type.
     *
     * @param array $data
     * @return User
     */
    public function login(array $data)
    {
        if (isset($data['email']) && !isset($data['phone'])) {

            $user = User::where('email', $data['email'])->first();
        } elseif (!isset($data['email']) && isset($data['phone'])) {

            $user = User::where('phone', $data['phone'])->first();
        } else {
            throw new \Exception('يجب أن تحتوي البيانات إما على البريد الإلكتروني أو رقم الهاتف.');
        }

        // تحقق أولًا إذا كان اليوزر موجود
        if (!$user) {
            return [
                'status' => 404,
                'user' => 'User not found'
            ];
        }

        if ($user->email_verified_at == null) {
            return response()->json(['message' => 'Please verify your email'], 401);
        }

        if (!Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid Credentials'], 401);
        }

        $token = $user->createToken($user->id . '-AuthToken')->plainTextToken;

        return [
            'status' => 200,
            'token' => $token,
            'user' => $user
        ];
    }
}
