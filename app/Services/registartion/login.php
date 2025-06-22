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
public function login(array $data): array
    {
        try {
            // التحقق من وجود إما البريد الإلكتروني أو الهاتف
            if (empty($data['email']) && empty($data['phone'])) {
                throw new \Exception('يجب تقديم إما البريد الإلكتروني أو رقم الهاتف.');
            }

            // البحث عن المستخدم
            $user = $this->findUser($data);

            if (!$user) {
                return $this->errorResponse('بيانات الاعتماد غير صحيحة.', 401);
            }

            // التحقق من كلمة المرور
            if (!Hash::check($data['password'], $user->password)) {
                return $this->errorResponse('بيانات الاعتماد غير صحيحة.', 401);
            }

            // التحقق من تفعيل البريد الإلكتروني
            if ($user->email_verified_at === null) {
                return $this->errorResponse('يرجى تفعيل حسابك عن طريق البريد الإلكتروني أولاً.', 401);
            }

            // إنشاء token
            $token = $user->createToken($user->id . '-AuthToken')->plainTextToken;

            return $this->successResponse($token, $user);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * البحث عن المستخدم
     */
    private function findUser(array $data): ?User
    {
        if (!empty($data['email'])) {
            return User::where('email', $data['email'])->first();
        }

        if (!empty($data['phone'])) {
            return User::where('phone', $data['phone'])->first();
        }

        return null;
    }

    /**
     * استجابة النجاح
     */
    private function successResponse(string $token, User $user): array
    {
        return [
            'status' => 200,
            'token' => $token,
            'user' => $user,
            'response' => response()->json([
                'message' => 'تم تسجيل الدخول بنجاح',
                'token' => $token,
                'user' => $user
            ], 200)
        ];
    }

    /**
     * استجابة الخطأ
     */
    private function errorResponse(string $message, int $statusCode): array
    {
        return [
            'status' => $statusCode,
            'response' => response()->json([
                'message' => $message,
                'errors' => ['auth' => $message]
            ], $statusCode)
        ];
    }

}
