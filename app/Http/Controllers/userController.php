<?php

namespace App\Http\Controllers;

use App\Mail\OTPMail;
use Illuminate\Http\Request;
use App\Models\user;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class userController extends Controller
{
    public function Get_my_info()
    {

        $user = user::find(auth()->user()->id);
        return response()->json([
            'status' => true,
            'user' => $user
        ]);
    }



    public function get_info($id)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Read-UsersProfiles')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
        $user = user::find($id);
        return response()->json([
            'status' => true,
            'user' => $user
        ]);
    }



    public function get_all(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Read-UsersProfiles')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
        try {
            $perPage = $request->get('per_page', 3); // افتراضي 15 عنصر في الصفحة
            $users = User::where('type', '=', '0')->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب المستخدمين',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }


    // public function update_my_info(Request $request)
    // {
    //     $user_id = auth()->user()->id;

    //     $user = user::find($user_id);
    //     $validator = Validator::make($request->all(), [
    //         "name" => "nullable|string|max:255",
    //         "email" => "nullable|email",
    //         "phone" => "nullable|string|max:20",
    //         "country" => "nullable|string|max:100",
    //         "password" => "nullable|confirmed|min:6",
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => false,
    //             'errors' => $validator->errors()
    //         ]);
    //     }

    //     // فقط حدث الحقول المسموح بها
    //     $data = $request->only(['name', 'email', 'phone', 'country']);

    //     if ($request->filled('password')) {
    //         $data['password'] = bcrypt($request->password);
    //     }

    //     $user->update($data);

    //     return response()->json([
    //         'status' => true,
    //         'user' => $user
    //     ]);
    // }

    public function update_my_info(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not authenticated.'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name'    => 'nullable|string|max:255',
            'email'   => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('users', 'temporary_email')->ignore($user->id),
            ],
            'phone'   => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()->first()
            ], 422);
        }
        $data = $request->only(['name', 'phone', 'country']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        $emailChanged = false;
        $newEmail = null;
        if ($request->filled('email') && $request->email !== $user->email) {
            $emailChanged = true;
            $newEmail = $request->email;
            $otp = random_int(100000, 999999);
            $user->temporary_email = $newEmail;
            $user->otp = Hash::make($otp);
            $user->email_verified_at = null;
        }
        $user->fill($data);
        $user->save();
        if ($emailChanged) {
            try {
                Mail::to($newEmail)->send(new OTPMail($otp, 'Email change verification'));
            } catch (\Exception $e) {
                $user->temporary_email = null;
                $user->otp = null;
                $user->save();

                return response()->json([
                    'status' => false,
                    'message' => 'Failed to send verification email. Please try again later.'
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'User info updated. Verification code has been sent to the new email.',
                'user' => $user
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'User info updated successfully.',
            'user' => $user
        ], 200);
    }
}
