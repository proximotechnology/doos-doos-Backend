<?php

namespace App\Http\Controllers\Registration;

use Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\registartion\LoginRequest; // Ensure the namespace is correct
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Services\registartion\login; // Ensure the namespace is correct

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite as FacadesSocialite;

class LoginController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Handle the registration of a new user.
     *
     * @param LoginRequest   $request
     * @return JsonResponse
     */

    protected $loginService;

    public function __construct(login $loginService)
    {
        $this->loginService = $loginService;
    }


    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'password' => 'required|string',
        ]);

        if (isset($data['email']) && !isset($data['phone'])) {
            $user = User::where('email', $data['email'])->first();
        } elseif (!isset($data['email']) && isset($data['phone'])) {
            $user = User::where('phone', $data['phone'])->first();
        } else {
            return response()->json([
                'message' => 'يرجى إدخال البريد الإلكتروني أو رقم الهاتف فقط، وليس كليهما.'
            ], 422);
        }

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->email_verified_at == null) {
            return response()->json(['message' => 'Please verify your email'], 401);
        }

        if (!Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid Credentials'], 401);
        }

        $token = $user->createToken($user->id . '-AuthToken')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'user' => $user,
        ]);
    }







    public function logout()
    {
        auth()->user()->tokens()->delete();

        return response()->json([
            "message" => "logged out"
        ]);
    }
}
