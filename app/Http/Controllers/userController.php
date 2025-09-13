<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\user;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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

        $user = user::find($id);
        return response()->json([
            'status' => true,
            'user' => $user
        ]);
    }



    public function get_all(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 3); // افتراضي 15 عنصر في الصفحة
            $users = User::paginate($perPage);

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


    public function update_my_info(Request $request)
    {
        $user_id = auth()->user()->id;

        $user = user::find($user_id);
        $validator = Validator::make($request->all(), [
            "name" => "nullable|string|max:255",
            "email" => "nullable|email",
            "phone" => "nullable|string|max:20",
            "country" => "nullable|string|max:100",
            "password" => "nullable|confirmed|min:6",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }

        // فقط حدث الحقول المسموح بها
        $data = $request->only(['name', 'email', 'phone', 'country']);

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->password);
        }

        $user->update($data);

        return response()->json([
            'status' => true,
            'user' => $user
        ]);
    }
}
