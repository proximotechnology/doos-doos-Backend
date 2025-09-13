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
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{


    //---------------------------API RESET PASSWORD & VERIFY EMAIL----------------------------------------

    // public function sendOTP(Request $request)
    // {

    //     $otp = rand(100000, 999999);

    //     $validator = Validator::make($request->all(), [
    //         'email' => 'required'

    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
    //             'status' => false
    //         ], 422);
    //     }

    //     $user = User::where('email', $request->email)->first();


    //     if (!$user) {
    //         return response()->json([
    //             'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
    //             'status' => false
    //         ], 422);
    //     }



    //     $user->update(['otp' => $otp]);


    //     $data['otp'] = $otp;

    //     Mail::to($request->email)->send(new OTPMail($otp, 'test'));

    //     return response()->json([
    //         'message' => 'تم ارسال الكود بنجاح',
    //         'status' => true
    //     ]);
    // }


    // public function receiveOTP(Request $request)
    // {


    //     $validator = Validator::make($request->all(), [
    //         'otp' => 'required|digits:6'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
    //             'status' => false
    //         ], 422);
    //     }

    //     $otp_user = $request->otp;

    //     $user = User::where('otp', $otp_user)->first();



    //     if (!$user) {
    //         return response()->json([
    //             'message' => 'الكود غير صحيح',
    //             'status' => false
    //         ], 422);
    //     }


    //     return response()->json([
    //         'message' => 'تم التحقق من الكود بنجاح',
    //         'status' => true
    //     ]);
    // }

    // public function resetpassword(Request $request)
    // {
    //     $validator = validator($request->all(), [
    //         'password' => 'required|confirmed|min:6',
    //         'otp' => 'required',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'message' => 'حدث خطأ أثناء التسجيل',
    //             'errors' => $validator->errors(), // رجع الأخطاء مصفوفة
    //             'status' => false
    //         ], 422);
    //     }

    //     $otp_user = $request->otp;

    //     $user = User::where('otp', $otp_user)->first();

    //     if (!$user) {
    //         return response()->json([
    //             'message' => 'رمز التحقق غير صحيح',
    //             'status' => false
    //         ], 422);
    //     }

    //     $user->update([
    //         'password' => Hash::make($request->password),
    //         'otp' => null
    //     ]);

    //     return response()->json([
    //         'message' => 'تم تغيير كلمة المرور بنجاح',
    //         'status' => true
    //     ]);
    // }




    public function sendOTP(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->getUser($request);
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'Account not registered'], Response::HTTP_BAD_REQUEST);
            }
            $otp_code = rand(100000, 999999);
            $user->otp = Hash::make($otp_code);
            $isSavedCode = $user->save();
            if ($isSavedCode) {
                Mail::to($request->email)->send(new OTPMail($otp_code, 'test'));
            }

            return response()->json(['status' => true, 'message' => 'Password reset code sent successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred',
                'error'   => $e->getMessage(), // الخطأ الحقيقي
                'trace'   => config('app.debug') ? $e->getTraceAsString() : null, // التريس يظهر بس إذا APP_DEBUG=true
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function receiveOTP(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|string',
                'otp' => 'required|string|min:6|max:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
            }
            $user = $this->getUser($request);
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'Account not registered'], Response::HTTP_BAD_REQUEST);
            }
            if (!Hash::check($request->input('otp'), $user->otp)) {
                return response()->json(['status' => false, 'message' => 'Activation otp error, try again'], Response::HTTP_BAD_REQUEST);
            }

            return response()->json(['status' => true, 'message' => 'Activation otp verified successfully'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred',
                'error'   => $e->getMessage(), // الخطأ الحقيقي
                'trace'   => config('app.debug') ? $e->getTraceAsString() : null, // التريس يظهر بس إذا APP_DEBUG=true
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function resetpassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6|confirmed',
                'code' => 'required|string|min:6|max:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => $validator->errors()->first()], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->getUser($request);
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'Account not registered'], Response::HTTP_BAD_REQUEST);
            }
            if (!$user->otp) {
                return response()->json(['status' => false, 'message' => 'No OTP generated, please request a new code'], Response::HTTP_BAD_REQUEST);
            }
            if (!Hash::check($request->input('code'), $user->otp)) {
                return response()->json(['status' => false, 'message' => 'Activation code error, try again'], Response::HTTP_BAD_REQUEST);
            }
            $user->password = Hash::make($request->input('password'));
            $user->otp = null;
            $user->save();
            return response()->json(['status' => true, 'message' => 'Reset password success'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred',
                'error'   => $e->getMessage(), // الخطأ الحقيقي
                'trace'   => config('app.debug') ? $e->getTraceAsString() : null, // التريس يظهر بس إذا APP_DEBUG=true
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }





    //     return response()->json([
    //         'message' => 'تم تغيير كلمة المرور بنجاح',
    //         'status' => true
    //     ]);
    // }

    public function verfiy_email(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'otp' => 'required|digits:6',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'حدث خطاء اثناء التسجيل: ' . $validator->errors(),
                'status' => false
            ], 422);
        }

        $otp_user = $request->otp;

        $user = User::where('otp', $otp_user)->first();



        if (!$user) {
            return response()->json([
                'message' => 'الكود غير صحيح',
                'status' => false
            ], 422);
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


    private function getUser(Request $request)
    {
        $user = null;
        $email = $request->input('email');
        $user = User::where('email', $email)->first();
        return $user;
    }
}
