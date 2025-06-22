<?php

namespace App\Http\Controllers\Registration;
use Laravel\Socialite\Facades\Socialite; // Add this line
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Helpers\OtpHelper; 
class GoogleAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();

    }



    public function callback()
    {
        $googleUser  = Socialite::driver('google')->stateless()->user();

        // Check if the user already exists
        $user = User::where('email', $googleUser ->getEmail())->first();

        if (!$user) {
            // Create a new user if not found
            $user = User::create([
                'name' => $googleUser ->getName(),
                'email' => $googleUser ->getEmail(),
                'password' => bcrypt(Str::random(16)), // Use Str::random instead of str_random
                'google_id' => $googleUser->id,
            ]);
        }

        OtpHelper::sendOtpEmail($user->id);

        
        // Log the user in
        Auth::login($user, true);
        $token = $user->createToken($user->name . '-AuthToken')->plainTextToken;

        return response()->json([
            'message' => 'User  login successfully',
            'access_token' => $token,
            'user' => $user,
        ]);
    }
}
