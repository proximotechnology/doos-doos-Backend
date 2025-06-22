<?php

namespace App\Http\Controllers;

use App\services\registartion\register;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use App\Helpers\OtpHelper;

class ForgetPasswordController extends Controller
{
    protected $userService;

    public function __construct(register $userService)
    {
        $this->userService = $userService;
    }


    public function forgetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email Sent successfully !'], 200);
        }

        OtpHelper::sendOtpEmail($user -> id);

        return response()->json(['message' => 'OTP sent successfully']);
    }

    public function resetPasswordByVerifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid email'], 400);
        }

        $otp = Cache::get('otp_' . $user->id);

        if (!$otp || $otp['otp'] != $request->otp || $otp['email'] != $request->email) {
            return response()->json(['message' => 'Invalid OTP'], 400);
        }

        Cache::forget('otp_' . $user->id);
        $newPassword = $this->userService->generateRandomPassword(8);
        OtpHelper::resetPassword($user -> id, $newPassword );

        return response()->json(['message' => 'OTP verified successfully, and new password sent to your email']);
    }

}
