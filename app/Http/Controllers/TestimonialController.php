<?php

namespace App\Http\Controllers;

use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
class TestimonialController extends Controller
{


    public function storeTestimonial_Admin(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('storeTestimonial_Admin')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $imageUrl = null;

            // حفظ الصورة بنفس طريقة تخزين صور السيارات
            if ($request->hasFile('image')) {
                $imageFile = $request->file('image');
                $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                $imagePath = 'testimonial_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
                $imageUrl = url('api/storage/' . $imagePath);
            }

            $testimonial = Testimonial::create([
                'name' => $request->name,
                'image' => $imageUrl,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'تم إنشاء الشهادة بنجاح',
                'data' => $testimonial
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء حفظ الشهادة: ' . $e->getMessage(),
            ], 500);
        }
    }




    public function getTestimonialsWithFilter_Admin(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('getTestimonialsWithFilter_Admin')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
        try {
            $query = Testimonial::query();

            // فلترة حسب التقييم
            if ($request->has('rating')) {
                $query->where('rating', $request->rating);
            }

            // فلترة حسب الاسم (بحث)
            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }


            // الترتيب حسب الأحدث
            $query->orderBy('created_at', 'desc');

            // الباجينيش إذا كان مطلوب
            $perPage = $request->has('per_page') ? $request->per_page : 15;
            $testimonials = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $testimonials
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء استرجاع الشهادات: ' . $e->getMessage(),
            ], 500);
        }
    }


    public function updateTestimonial_admin(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('updateTestimonial_admin')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }

        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return response()->json([
                'status' => false,
                'message' => 'الشهادة غير موجودة',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updateData = $request->only(['name', 'rating', 'comment']);

            // تحديث الصورة إذا تم رفع جديدة
            if ($request->hasFile('image')) {
                // حذف الصورة القديمة إذا كانت موجودة
                if ($testimonial->image) {
                    $oldImagePath = str_replace(url('api/storage/'), '', $testimonial->image);
                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }

                // حفظ الصورة الجديدة
                $imageFile = $request->file('image');
                $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                $imagePath = 'testimonial_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($imageFile));
                $updateData['image'] = url('api/storage/' . $imagePath);
            }

            $testimonial->update($updateData);

            return response()->json([
                'status' => true,
                'message' => 'تم تحديث الشهادة بنجاح',
                'data' => $testimonial
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تحديث الشهادة: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function deleteTestimonial_Admin($id)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('deleteTestimonial_Admin')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }


        $testimonial = Testimonial::find($id);

        if (!$testimonial) {
            return response()->json([
                'status' => false,
                'message' => 'الشهادة غير موجودة',
            ], 404);
        }

        try {
            // حذف الصورة المرتبطة إذا كانت موجودة
            if ($testimonial->image) {
                $imagePath = str_replace(url('api/storage/'), '', $testimonial->image);
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            $testimonial->delete();

            return response()->json([
                'status' => true,
                'message' => 'تم حذف الشهادة بنجاح',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء حذف الشهادة: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function getTestimonial_admin($id)
    {
        try {
            $testimonial = Testimonial::find($id);

            if (!$testimonial) {
                return response()->json([
                    'status' => false,
                    'message' => 'الشهادة غير موجودة',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $testimonial
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء استرجاع الشهادة: ' . $e->getMessage(),
            ], 500);
        }
    }





    public function getTestimonialsWithFilter_User(Request $request)
    {
        try {
            $query = Testimonial::query();

            // فلترة حسب التقييم
            if ($request->has('rating')) {
                $query->where('rating', $request->rating);
            }

            // فلترة حسب الاسم (بحث)
            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }


            // الترتيب حسب الأحدث
            $query->orderBy('created_at', 'desc');

            // الباجينيش إذا كان مطلوب
            $perPage = $request->has('per_page') ? $request->per_page : 15;
            $testimonials = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $testimonials
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء استرجاع الشهادات: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function getTestimonial_user($id)
    {
        try {
            $testimonial = Testimonial::find($id);

            if (!$testimonial) {
                return response()->json([
                    'status' => false,
                    'message' => 'الشهادة غير موجودة',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $testimonial
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء استرجاع الشهادة: ' . $e->getMessage(),
            ], 500);
        }
    }


}
