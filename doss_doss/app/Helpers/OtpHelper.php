<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\SendOtpMail;
use App\Mail\ResetPasswordMail;
use App\Mail\EmailVerificationMail;
use App\Models\User;

class OtpHelper
{
    public static function sendOtpEmail($id)
    {
        $user = User::findOrFail($id);
        $otp = Str::random(6);

        // Store OTP in cache with ID and email, set expiration time (e.g., 5 minutes)
        Cache::put('otp_' . $user->id, ['otp' => $otp, 'email' => $user->email], 300);

        // Send OTP via email
        Mail::to($user->email)->send(new SendOtpMail($otp));
        return $otp;
    }

    public static function resetPassword($id, $newPassword)
    {
        $user = User::findOrFail($id);
        $user->password = bcrypt($newPassword);
        $user->save();
        Mail::to($user->email)->send(new ResetPasswordMail($newPassword));
    }

    public static function sendVerificationEmail($id)
    {
        $user = User::findOrFail($id);
        $token = Str::random(32);

        // تخزين رمز التحقق في الكاش
        Cache::put('verify_' . $user->id, $token, 3600);

        // إنشاء رابط التحقق
        $verificationLink = url('/api/verify-email?token=' . $token . '&id=' . $user->id);

        // إرسال البريد الإلكتروني
        Mail::to($user->email)->send(new EmailVerificationMail($verificationLink));
    }



    public static function resendVerificationEmail($id)
    {
        $user = User::findOrFail($id);

        // التحقق مما إذا كان البريد الإلكتروني قد تم التحقق منه
        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'تم بالفعل التحقق من البريد الإلكتروني'
            ], 400);
        }

        $token = Str::random(32);

        // تخزين رمز التحقق في الكاش
        Cache::put('verify_' . $user->id, $token, 3600);

        // إنشاء رابط التحقق الجديد
        $verificationLink = url('/api/verify-email?token=' . $token . '&id=' . $user->id);

        // إرسال البريد الإلكتروني
        Mail::to($user->email)->send(new EmailVerificationMail($verificationLink));

        return response()->json([
            'message' => 'تم إرسال رابط التحقق بنجاح'
        ]);
    }
}
