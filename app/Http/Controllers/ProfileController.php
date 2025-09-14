<?php

namespace App\Http\Controllers;

use App\Models\profile;
use Illuminate\Http\Request;

use App\Events\public_notifiacation;
use App\Events\PrivateNotificationEvent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
//call PrivateChannel
use Illuminate\Broadcasting\PrivateChannel;


use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $profile = profile::where('user_id', $user->id)->first();


        $type = 'subscripe';
        $message = 'subscripe';
        event(new public_notifiacation('subscripe', 'subscripe'));

        // event(new PrivateNotificationEvent('تمت عملية الدفع بنجاح', 'success', $user->id));



        Log::info('Event fired');
        return response()->json($profile);
    }

    public function get_my_profile()
    {
        $user = auth()->user();
        $profile = profile::where('user_id', $user->id)->first();


        return response()->json($profile);
    }



    public function get_user_profile($id)
    {

        $profile = profile::where('user_id', $id)->first();
        return response()->json($profile);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $profile = profile::where('user_id', $user->id)->first();

        if ($profile) {
            return response()->json(['error' => 'Profile already exists for this user.'], 400);
        }

        $request['user_id'] = $user->id;

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
            'address_1' => 'required|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'zip_code' => 'required|string|max:20',
            'city' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // إضافة mimes وتعديل max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $data = $request->all();

            // معالجة صورة الملف الشخصي بنفس طريقة storeCar
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = Str::random(32) . '.' . $image->getClientOriginalExtension();
                $imagePath = 'profile_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($image));
                $data['image'] = url('api/storage/' . $imagePath); // استخدام url بدلاً من المسار فقط
            }

            $profile = profile::create($data);

            DB::commit();

            return response()->json($profile, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Store profile failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'حدث خطأ أثناء حفظ الملف الشخصي: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $profile = profile::where('user_id', $user->id)->first();

        if (!$profile) {
            return response()->json(['error' => 'Profile not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'user_id' => 'sometimes|integer|exists:users,id',
            'address_1' => 'sometimes|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'zip_code' => 'sometimes|string|max:20',
            'city' => 'sometimes|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048', // إضافة mimes
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $data = $request->all();

            // معالجة صورة الملف الشخصي بنفس طريقة storeCar
            if ($request->hasFile('image')) {
                // حذف الصورة القديمة إذا كانت موجودة
                if ($profile->image) {
                    $oldImagePath = str_replace(url('api/storage/'), '', $profile->image);
                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }

                $image = $request->file('image');
                $imageName = Str::random(32) . '.' . $image->getClientOriginalExtension();
                $imagePath = 'profile_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($image));
                $data['image'] = url('api/storage/' . $imagePath);
            }

            $profile->update($data);

            DB::commit();

            return response()->json([
                'message' => 'Profile updated successfully',
                'data' => $profile
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update profile failed: ' . $e->getMessage());
            return response()->json([
                'error' => 'حدث خطأ أثناء تحديث الملف الشخصي: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(profile $profile)
    {
        $profile->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
