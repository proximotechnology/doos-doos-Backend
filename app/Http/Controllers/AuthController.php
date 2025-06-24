<?php

namespace App\Http\Controllers;

use App\Models\qtap_admins;
use App\Models\qtap_affiliate;
use App\Models\clients_logs;
use App\Models\User;
use App\Models\qtap_clients_brunchs;
use App\Models\restaurant_user_staff;
use App\Models\qtap_clients;
use App\Models\restaurant_staff;
use App\Models\restaurant_users;
use App\Models\users_logs;
use App\Models\affiliate_log;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;


use Illuminate\Support\Facades\Cookie;

use Illuminate\Support\Str;

use App\Mail\OTPMail;

use Illuminate\Support\Facades\Mail;

use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth; // أضف هذا لضمان عمل Auth بشكل صحيح
use Illuminate\Validation\Rule;



class AuthController extends Controller
{





    //---------------------------API RESET PASSWORD & VERIFY EMAIL----------------------------------------

    public function sendOTP(Request $request)
    {

        $otp = rand(100000, 999999);

        $validator = Validator::make($request->all(), [
            'email' => 'required'

        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
                'status' => false
            ] , 422);
        }

        $user = User::where('email', $request->email)->first();


        if (!$user) {
            return response()->json([
                'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
                'status' => false
            ] , 422);
        }



        $user->update(['otp' => $otp]);


        $data['otp'] = $otp;

        Mail::to($request->email)->send(new OTPMail($otp, 'test'));

        return response()->json([
            'message' => 'تم ارسال الكود بنجاح',
            'status' => true
        ]);
    }


    public function receiveOTP(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
                'status' => false
            ] , 422);
        }

        $otp_user = $request->otp;

        $user = User::where('otp', $otp_user)->first();



        if (!$user) {
            return response()->json([
                'message' => 'الكود غير صحيح',
                'status' => false
            ],422);
        }


        return response()->json([
            'message' => 'تم التحقق من الكود بنجاح',
            'status' => true
        ]);
    }


    public function resetpassword(Request $request)
    {

        $validator = validator($request->all(), [
            'password' => 'sometimes|confirmed',
            'otp' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
                'status' => false
            ] , 422);
        }


        $otp_user = $request->otp;

        $user = User::where('otp', $otp_user)->first();


        if (!$user) {
            return response()->json([
                'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
                'status' => false
            ] , 422);
        }

        $user->update([

            'password' => Hash::make($request->password),
            'otp' => null
        ]);




        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح',
            'status' => true
        ]);
    }

    public function verfiy_email(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
                'status' => false
            ] , 422);
        }

        $otp_user = $request->otp;

        $user = User::where('otp', $otp_user)->first();



        if (!$user) {
            return response()->json([
                'message' => 'الكود غير صحيح',
                'status' => false
            ] , 422);
        }




        $user->update([
            'email_verified_at' => now(),
            'otp' => null
        ]);

        return response()->json([
            'message' => 'تم التحقق من الكود بنجاح',
            'status' => true
        ]);
    }
}
