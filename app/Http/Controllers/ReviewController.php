<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Cars;
use App\Models\Order_Booking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class ReviewController extends Controller
{



public function my_review(Request $request)
{
    try {
        $user = auth()->user();

        $validate = Validator::make($request->all(), [
            'status' => 'nullable|in:complete,pending',
            'per_page' => 'nullable|integer|min:1|max:100'
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 400);
        }

        // تحميل العلاقات مع تحديد الأعمدة المطلوبة فقط
        $query = Review::with([
            'user' => function ($query) {
                $query->select('id', 'name');
            },
            'user.profile' => function ($query) {
                $query->select('id', 'user_id', 'image');
            }
        ])->where('user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // استخدام Pagination مع تحديد الأعمدة المطلوبة
        $perPage = $request->get('per_page', 3);
        $reviews = $query->select('id', 'user_id', 'car_id', 'rating', 'status', 'comment', 'created_at')
                        ->paginate($perPage);

        // تنسيق البيانات
        $formattedReviews = $reviews->getCollection()->map(function ($review) {
            return [
                'id' => $review->id,
                'rating' => $review->rating,
                'status' => $review->status,
                'comment' => $review->comment,
                'created_at' => $review->created_at,
                'user' => [
                    'name' => $review->user->name,
                    'image' =>$review->user->profile->image
                              ?? null
                ]
            ];
        });

        $reviews->setCollection($formattedReviews);

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);

    } catch (\Exception $e) {
        Log::error('Error fetching user reviews: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء جلب التقييمات',
            'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
        ], 500);
    }
}

    public function update_owner_review(Request $request, $review_id)
    {

        // جلب التقييم مع السيارة ومالكها
        $review = Review::with(['car' => function ($query) {
            $query->with('owner');
        }])->find($review_id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        // التحقق من وجود السيارة المرتبطة بالتقييم
        if (!$review->car) {
            return response()->json([
                'success' => false,
                'message' => 'Car not found for this review',
            ], 404);
        }

        $user = auth()->user();

        // التحقق إذا كان المستخدم هو مالك السيارة
        if ($user->id != $review->car->owner_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not the owner of this car',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:pending,complete,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];

            if ($request->has('rating')) {
                $updateData['rating'] = $request->rating;
            }

            if ($request->has('comment')) {
                $updateData['comment'] = $request->comment;
            }

            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            // تحديث التقييم فقط إذا كانت هناك بيانات للتحديث
            if (!empty($updateData)) {
                $review->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully by owner',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //majd
    //Mohammad
    public function my_review_owner(Request $request)
    {
        try {
            $user = auth()->user();

            // جلب سيارات هذا المالك
            $ownerCarIds = Cars::where('owner_id', $user->id)->pluck('id');

            // تحقق من الفلترة
            $validate = Validator::make($request->all(), [
                'status' => 'nullable|in:complete,pending',
                'car_id' => 'nullable|exists:cars,id',
                'rating' => 'nullable|numeric|min:1|max:5',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validate->fails()) {
                return response()->json(['error' => $validate->errors()], 400);
            }

            // تأكد أن السيارة المحددة تعود لهذا المالك
            if ($request->filled('car_id') && !$ownerCarIds->contains($request->car_id)) {
                return response()->json(['error' => 'You do not own this car.'], 403);
            }

            // تحميل العلاقات مع تحديد الأعمدة المطلوبة فقط
            $query = Review::with([
                'user' => function ($query) {
                    $query->select('id', 'name');
                },
                'user.profile' => function ($query) {
                    $query->select('id', 'user_id', 'image');
                }
            ])->whereIn('car_id', $ownerCarIds)
            ->where('user_id', '!=', $user->id); // استبعاد تقييمات المالك نفسه

            // فلترة حسب الحالة إن وُجدت
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // فلترة حسب تقييم النجوم إن وُجد
            if ($request->filled('rating')) {
                $query->where('rating', $request->rating);
            }

            // فلترة حسب car_id إن وُجد
            if ($request->filled('car_id')) {
                $query->where('car_id', $request->car_id);
            }

            // استخدام Pagination مع تحديد الأعمدة المطلوبة
            $perPage = $request->get('per_page', 3);
            $reviews = $query->select('id', 'user_id', 'car_id', 'rating', 'status', 'comment', 'created_at')
                            ->paginate($perPage);

            // تنسيق البيانات بنفس طريقة my_review
            $formattedReviews = $reviews->getCollection()->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'status' => $review->status,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                    'user' => [
                        'name' => $review->user->name,
                        'image' => $review->user->profile->image ?? null
                    ]
                ];
            });

            $reviews->setCollection($formattedReviews);

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching owner reviews: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التقييمات',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function all_review(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'status' => 'nullable|in:complete,pending',
                'car_id' => 'nullable|exists:cars,id',
                'user_id' => 'nullable|exists:users,id',
                'rating' => 'nullable|numeric|min:1|max:5',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validate->fails()) {
                return response()->json(['error' => $validate->errors()], 400);
            }

            // تحميل العلاقات مع تحديد الأعمدة المطلوبة فقط
            $query = Review::with([
                'user' => function ($query) {
                    $query->select('id', 'name', 'email');
                },
                'user.profile' => function ($query) {
                    $query->select('id', 'user_id', 'image', 'first_name', 'last_name');
                },
            ]);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('car_id')) {
                $query->where('car_id', $request->car_id);
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('rating')) {
                $query->where('rating', $request->rating);
            }

            // استخدام Pagination مع تحديد الأعمدة المطلوبة من Review
            $perPage = $request->get('per_page', 5);
            $reviews = $query->select('id', 'user_id', 'car_id', 'rating', 'status', 'comment', 'created_at', 'updated_at')
                            ->paginate($perPage);

            // تنسيق البيانات
            $formattedReviews = $reviews->getCollection()->map(function ($review) {
                return [
                    'id' => $review->id,
                    'user_id' => $review->user_id,
                    'car_id' => $review->car_id,
                    'rating' => $review->rating,
                    'status' => $review->status,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                    'updated_at' => $review->updated_at,
                    'user' => $review->user ? [
                        'name' => $review->user->name,
                        'profile' => $review->user->profile ? [
                            'image' => $review->user->profile->image ?? null,

                        ] : null
                    ] : null,

                ];
            });

            $reviews->setCollection($formattedReviews);

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all reviews: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب التقييمات',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        $user = auth()->user();

        if ($user->id != $review->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'This review is not yours',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $review->update([
                'rating' => $request->rating,
                'comment' => $request->comment,
                'status' => 'complete' // Automatically set to complete
            ]);

            // Update car's average rating

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function delete_admin($id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully',
        ]);
    }


    public function update_admin(Request $request, $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => 'Review not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:pending,complete,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updateData = [];

            if ($request->has('rating')) {
                $updateData['rating'] = $request->rating;
            }

            if ($request->has('comment')) {
                $updateData['comment'] = $request->comment;
            }

            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            $review->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully by admin',
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function delete_owner($id)
    {
        $user = auth()->user();

        // جلب التقييم
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['error' => 'Review not found'], 404);
        }

        // جلب سيارات المالك الحالي
        $ownerCarIds = cars::where('owner_id', $user->id)->pluck('id');

        // التحقق أن التقييم يخص سيارة يملكها المستخدم
        if (!$ownerCarIds->contains($review->car_id)) {
            return response()->json(['error' => 'You do not have permission to delete this review'], 403);
        }

        // حذف التقييم
        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review delete done',
        ]);
    }


    public function delete_user($id)
    {
        $user = auth()->user();

        // جلب التقييم
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['error' => 'Review not found'], 404);
        }

        // التحقق أن التقييم يعود لهذا المستخدم
        if ($review->user_id !== $user->id) {
            return response()->json(['error' => 'You do not have permission to delete this review'], 403);
        }

        // حذف التقييم
        $review->delete();

        return response()->json([
            'success' => true,
            'message' => 'Review delete done',
        ]);
    }




    public function store(Request $request, $car_id)
    {
        $user = auth()->user();

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the user has already reviewed this car
      /*  $existingReview = Review::where('user_id', $user->id)
            ->where('car_id', $car_id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this car'
            ], 409); // 409 Conflict
        }*/

        // Check if the user has a completed booking for this car
        $hasCompletedBooking = Order_Booking::where('user_id', $user->id)
            ->where('car_id', $car_id)
            ->exists();

        if (!$hasCompletedBooking) {
            return response()->json([
                'success' => false,
                'message' => 'You must complete a booking for this car before reviewing it'
            ], 403);
        }

        try {
            $review = Review::create([
                'user_id' => $user->id,
                'car_id' => $car_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'status' => 'complete' // or 'pending' if you want admin approval
            ]);

            // Optionally update car's average rating
            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully',
                'data' => $review
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function B_car($car_id, Request $request)
    {
        try {
            // تحميل العلاقات مع تحديد الأعمدة المطلوبة فقط
            $query = Review::with([
                'user' => function ($query) {
                    $query->select('id', 'name');
                },
                'user.profile' => function ($query) {
                    $query->select('id', 'user_id', 'image');
                }
            ])->where('car_id', $car_id);

            // فلترة حسب الحالة إذا وجدت
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // فلترة حسب التقييم إذا وجد
            if ($request->has('rating')) {
                $query->where('rating', $request->rating);
            }

            // ترتيب النتائج (الأحدث أولاً)
            $query->orderBy('created_at', 'desc');

            // استخدام Pagination مع تحديد الأعمدة المطلوبة
            $perPage = $request->get('per_page', 10);
            $reviews = $query->select('id', 'user_id', 'car_id', 'rating', 'status', 'comment', 'created_at')
                            ->paginate($perPage);

            // تنسيق البيانات بنفس طريقة الدوال الأخرى
            $formattedReviews = $reviews->getCollection()->map(function ($review) {
                return [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'status' => $review->status,
                    'comment' => $review->comment,
                    'created_at' => $review->created_at,
                    'user' => [
                        'name' => $review->user->name,
                        'image' => $review->user->profile->image ?? null
                    ]
                ];
            });

            $reviews->setCollection($formattedReviews);

            return response()->json([
                'success' => true,
                'data' => $reviews,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching car reviews: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }
}
