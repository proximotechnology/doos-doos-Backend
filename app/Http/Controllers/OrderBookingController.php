<?php

namespace App\Http\Controllers;

use App\Models\Driver_Price;
use App\Models\Review;
use App\Models\Driver_license;
use App\Models\User;
use App\Models\Order_Booking;
use App\Models\Cars;
use App\Models\Contract;
use App\Models\Represen_Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\SMSService;
use App\Models\ContractItem;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use App\Helpers\PaymentHelper;



class OrderBookingController extends Controller
{



    public function store(Request $request, $id)
    {
        $user = auth()->user();
        $request['user_id'] = $user->id;
        $request['car_id'] = $id;

        // Define all validation rules
        $validationRules = [
            'user_id' => 'required|exists:users,id',
            'car_id' => 'required|exists:cars,id',
            'date_from' => 'required|date|after_or_equal:today',
            'date_end' => 'required|date|after:date_from',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|string|max:255',
            'with_driver' => 'required|boolean',
            'expiration_date' => 'nullable|date|after_or_equal:today',
            'number' => 'nullable|numeric',
            'payment_method' => 'required|string|in:montypay,cash',
            'driver_type' => 'required|string|in:pick_up,mail_in',
            'frontend_success_url' => 'required|url', // رابط التوجيه بعد النجاح
            'frontend_cancel_url' => 'required|url', // رابط التوجيه عند الإلغاء
        ];

        // Add conditional validation based on delivery_type
        if ($request->driver_type == 'pick_up') {
            $validationRules['station_id'] = 'required|exists:stations,id';
        } elseif ($request->driver_type == 'mail_in') {
            $validationRules['zip_code'] = 'required|numeric';
        }

        // Validate the request
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في التحقق',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check car availability
        $car = Cars::with(['model', 'owner'])->find($request->car_id);
        if (!$car || $car->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'السيارة غير متاحة للحجز حالياً',
            ], 404);
        }

        // Validate booking dates
        $dateFrom = Carbon::parse($request->date_from);
        $dateEnd = Carbon::parse($request->date_end);
        $days = $dateFrom->diffInDays($dateEnd);

        if ($days < $car->min_day_trip) {
            return response()->json([
                'status' => false,
                'message' => 'مدة الحجز أقل من الحد الأدنى المسموح به: ' . $car->min_day_trip . ' أيام',
            ], 422);
        }

        if (!is_null($car->max_day_trip) && $days > $car->max_day_trip) {
            return response()->json([
                'status' => false,
                'message' => 'مدة الحجز تتجاوز الحد الأقصى المسموح به: ' . $car->max_day_trip . ' أيام',
            ], 422);
        }


        // التحقق من الحجوزات الأخرى لأي مستخدم
        $existingBooking = Order_Booking::where('car_id', $request->car_id)
            ->where(function ($query) {
                $query->whereIn('status', ['pending', 'confirm', 'picked_up', 'Returned'])
                    ->orWhere(function ($q) {
                        $q->where('status', 'Completed')
                            ->where('completed_at', '>=', now()->subHours(12));
                    });
            })
            ->where(function ($query) use ($dateFrom, $dateEnd) {
                $query->whereBetween('date_from', [$dateFrom, $dateEnd])
                    ->orWhereBetween('date_end', [$dateFrom, $dateEnd])
                    ->orWhere(function ($q) use ($dateFrom, $dateEnd) {
                        $q->where('date_from', '<', $dateFrom)
                            ->where('date_end', '>', $dateEnd);
                    });
            })
            ->exists();

        if ($existingBooking) {
            return response()->json([
                'status' => false,
                'message' => 'السيارة محجوزة بالفعل في الفترة المطلوبة'
            ], 422);
        }

        // Calculate total price
        $total_price = $car->price * $days;

        if ($request->with_driver) {
            $driver_price = Driver_Price::first();
            if (!$driver_price) {
                return response()->json([
                    'status' => false,
                    'message' => 'لم يتم تحديد سعر السائق',
                ], 422);
            }
            $total_price += $driver_price->price;
        }

        DB::beginTransaction();

        try {
            // Prepare booking data
            $bookingData = [
                'user_id' => $request->user_id,
                'car_id' => $request->car_id,
                'date_from' => Carbon::parse($request->date_from),
                'date_end' => Carbon::parse($request->date_end),
                'with_driver' => $request->with_driver,
                'total_price' => $total_price,
                'payment_method' => $request->payment_method,
                'driver_type' => $request->driver_type,
                'status' => 'draft',
                'is_paid' => 0, // غير مدفوع في البداية
                'frontend_success_url' => $request->frontend_success_url,
                'frontend_cancel_url' => $request->frontend_cancel_url,
            ];

            // Add location data based on delivery type
            if ($request->driver_type == 'pick_up') {
                $bookingData['station_id'] = $request->station_id;
            } elseif ($request->driver_type == 'mail_in') {
                $bookingData['zip_code'] = $request->zip_code;
            }

            // Create the booking
            $booking = Order_Booking::create($bookingData);

            // إنشاء العقد المرتبط بالحجز
            $contract = Contract::create([
                'order_booking_id' => $booking->id,
                'status' => 'pending',
            ]);

            $contractItems = ContractItem::all()->pluck('item')->toArray();

            // تخزين عناصر العقد كـ JSON في حقل contract_items
            $contract->update([
                'contract_items' => json_encode($contractItems)
            ]);

            // جلب أرقام الهواتف
            $userPhone = $user->phone; // رقم المستخدم
            $carOwnerPhone = $car->owner->phone; // رقم صاحب السيارة

            // توليد OTPs
            $userOtp = rand(1000, 9999); // OTP للمستخدم
            $ownerOtp = rand(1000, 9999); // OTP لصاحب السيارة

            $userOtp = 12345; // OTP للمستخدم
            $ownerOtp = 12345; // OTP لصاحب السيارة

            // تخزين OTPs في الكاش
            $otpData = [
                'user_otp' => $userOtp,
                'owner_otp' => $ownerOtp,
                'attempts' => 0, // عدد محاولات التحقق
                'expires_at' => now()->addMinutes(15) // انتهاء الصلاحية بعد 15 دقيقة
            ];

            // تخزين البيانات في الكاش لمدة 15 دقيقة
            Cache::put('contract_otp_' . $contract->id, $otpData, now()->addMinutes(15));

            // Handle driver license if needed
            if ($user->has_license == 0) {
                $existing_license = Driver_license::where('user_id', $user->id)
                    ->where('number', $request->number)
                    ->first();

                if ($existing_license) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'رقم الرخصة موجود مسبقاً',
                    ], 422);
                }

                $licenseData = [
                    'user_id' => $user->id,
                    'country' => $request->country,
                    'state' => $request->state,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'birth_date' => $request->birth_date,
                    'expiration_date' => $request->expiration_date,
                    'number' => $request->number,
                ];

                if ($request->hasFile('image_license')) {
                    $path = $request->file('image_license')->store('licenses', 'public');
                    $licenseData['image'] = $path;
                }

                Driver_license::create($licenseData);
                $user->has_license = 1;
                $user->save();
            }

            // إذا كانت طريقة الدفع cash، لا نحتاج لإنشاء رابط دفع
            if ($request->payment_method === 'cash') {
                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'تم إنشاء الحجز بنجاح (الدفع نقداً) وتم إرسال رموز التحقق',
                    'data' => [
                        'booking' => $booking->load(['car', 'user']),
                        'contract' => $contract,
                        'payment_url' => null,
                        'otp_sent' => true
                    ],
                ], 201);
            }

            // إذا كانت طريقة الدفع montypay، ننشئ رابط دفع MontyPay
            if ($request->payment_method === 'montypay') {
                $paymentResult = PaymentHelper::createMontyPaySession($booking, $user);

                if ($paymentResult['success']) {
                    $paymentData = $paymentResult['data'];

                    PaymentHelper::createPaymentRecord($booking, $user, $paymentData);

                    DB::commit();

                    return response()->json([
                        'status' => true,
                        'message' => 'تم إنشاء الحجز بنجاح وتم إرسال رموز التحقق، يرجى إتمام الدفع',
                        'data' => [
                            'booking' => $booking->load(['car', 'user']),
                            'contract' => $contract,
                            'payment_url' => $paymentData['redirect_url'] ?? null,
                            'otp_sent' => true
                        ],
                    ], 201);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'فشل في إنشاء جلسة الدفع',
                        'error' => $paymentResult['error'],
                    ], 400);
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('فشل إنشاء الحجز: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'فشل إنشاء الحجز',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function createPaymentForBooking(Request $request)
    {
        $user = auth()->user();

        $validationRules = [
            'booking_id' => 'required|exists:order__bookings,id',
        ];

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // البحث عن الحجز المعلّق وغير المدفوع
            $booking = Order_Booking::where('id', $request->booking_id)
                ->where('user_id', $user->id)
                ->where('status', 'draft')
                ->where('is_paid', 0)
                ->first();

            if (!$booking) {
                return response()->json([
                    'status' => false,
                    'message' => 'الحجز غير موجود أو تم دفعه مسبقاً أو ليس بحالة معلقة',
                ], 404);
            }

            // حذف أي مدفوعات معلقة سابقة لهذا الحجز
            $deletedCount = PaymentHelper::deletePendingPayments($booking->id);

            if ($deletedCount > 0) {
                Log::info('تم حذف ' . $deletedCount . ' مدفوعات معلقة قديمة للحجز: ' . $booking->id);
            }

            // إنشاء جلسة دفع باستخدام الـ Helper
            $paymentResult = PaymentHelper::createMontyPaySession($booking, $user);

            if ($paymentResult['success']) {
                $paymentData = $paymentResult['data'];

                // إنشاء سجل الدفع باستخدام الـ Helper
                PaymentHelper::createPaymentRecord($booking, $user, $paymentData);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'تم إنشاء رابط الدفع بنجاح',
                    'data' => [
                        'booking' => $booking->load(['car', 'user']),
                        'payment_url' => $paymentData['redirect_url'] ?? null,
                    ],
                ], 200);
            } else {
                // فشل في إنشاء جلسة الدفع
                DB::rollBack();

                Log::error('MontyPay failed for existing booking: ' . $booking->id, [
                    'error' => $paymentResult['error']
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'فشل في إنشاء جلسة الدفع',
                    'error' => $paymentResult['error'],
                ], 400);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create payment for booking failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء إنشاء رابط الدفع',
                'error' => $e->getMessage(),
            ], 500);
        }
    }






















    public function verifyContractOtp(Request $request)
    {


        $validate = Validator::make($request->all(), [
            'contract_id' => 'required|exists:contracts,id',
            'otp' => 'required|numeric',
            'user_type' => 'required|in:user,owner', // 1 for user, 2 for owner
        ]);

        if ($validate->fails()) {
            return response()->json(['errors' => $validate->errors()]);
        }


        $user = auth()->user();
        $contract = Contract::with(['booking.user', 'booking.car.owner'])->findOrFail($request->contract_id);

        // التحقق من صلاحية المستخدم لطلب إعادة الإرسال
        if ($request->user_type == 'user') {
            // فقط صاحب الحجز (المستأجر) يمكنه طلب إعادة إرسال OTP الخاص به
            if ($user->id != $contract->booking->user_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'غير مصرح لك بإعادة إرسال رمز التحقق'
                ], 403);
            }
            $phoneNumber = $contract->booking->user->phone;
        } else {
            // فقط صاحب السيارة يمكنه طلب إعادة إرسال OTP الخاص به
            if ($user->id != $contract->booking->car->owner->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'غير مصرح لك بإعادة إرسال رمز التحقق'
                ], 403);
            }
            $phoneNumber = $contract->booking->car->owner->phone;
        }

        $cacheKey = 'contract_otp_' . $contract->id;
        $cachedOtp = Cache::get($cacheKey);

        if (!$cachedOtp) {
            return response()->json([
                'status' => false,
                'message' => 'انتهت صلاحية رمز التحقق أو غير صالح'
            ], 404);
        }

        // التحقق من انتهاء الصلاحية
        if (now()->gt($cachedOtp['expires_at'])) {
            Cache::forget($cacheKey);
            return response()->json([
                'status' => false,
                'message' => 'انتهت صلاحية رمز التحقق'
            ], 422);
        }

        // زيادة عدد المحاولات
        $cachedOtp['attempts']++;
        Cache::put($cacheKey, $cachedOtp, now()->diffInSeconds($cachedOtp['expires_at']));

        // التحقق من عدد المحاولات
        if ($cachedOtp['attempts'] > 3) {
            Cache::forget($cacheKey);
            return response()->json([
                'status' => false,
                'message' => 'تم تجاوز الحد الأقصى لعدد المحاولات'
            ], 422);
        }

        // التحقق حسب نوع المستخدم
        if ($request->user_type == 'user') {
            if ($cachedOtp['user_otp'] == $request->otp) {
                // تحديث حقل OTP الخاص بالمستخدم
                $contract->update([
                    'otp_user' => 'verified'
                ]);

                // إذا تم التحقق من كلا الطرفين، نحدث حالة العقد
                $this->checkCompleteVerification($contract, $cacheKey);

                return response()->json([
                    'status' => true,
                    'message' => 'تم التحقق بنجاح كعميل',
                    'contract' => $contract,
                    'verified_as' => 'user'
                ]);
            }
        } elseif ($request->user_type == 'owner') {
            if ($cachedOtp['owner_otp'] == $request->otp) {
                // تحديث حقل OTP الخاص بصاحب السيارة
                $contract->update([
                    'otp_renter' => 'verified'
                ]);

                // إذا تم التحقق من كلا الطرفين، نحدث حالة العقد
                $this->checkCompleteVerification($contract, $cacheKey);

                return response()->json([
                    'status' => true,
                    'message' => 'تم التحقق بنجاح كمالك',
                    'contract' => $contract,
                    'verified_as' => 'owner'
                ]);
            }
        }

        // إذا وصلنا إلى هنا يعني أن التحقق فشل
        $remainingAttempts = 3 - $cachedOtp['attempts'];

        return response()->json([
            'status' => false,
            'message' => 'رمز التحقق غير صحيح',
            'remaining_attempts' => $remainingAttempts
        ], 422);
    }

    public function resendOtp(Request $request)
    {



        $validate = Validator::make($request->all(), [
            'contract_id' => 'required|exists:contracts,id',
            'user_type' => 'required|in:user,owner'
        ]);

        if ($validate->fails()) {
            return response()->json(['errors' => $validate->errors()]);
        }



        $user = auth()->user();
        $contract = Contract::with(['booking.user', 'booking.car.owner'])->findOrFail($request->contract_id);

        // التحقق من صلاحية المستخدم لطلب إعادة الإرسال
        if ($request->user_type == 'user') {
            // فقط صاحب الحجز (المستأجر) يمكنه طلب إعادة إرسال OTP الخاص به
            if ($user->id != $contract->booking->user_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'غير مصرح لك بإعادة إرسال رمز التحقق'
                ], 403);
            }
            $phoneNumber = $contract->booking->user->phone;
        } else {
            // فقط صاحب السيارة يمكنه طلب إعادة إرسال OTP الخاص به
            if ($user->id != $contract->booking->car->owner->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'غير مصرح لك بإعادة إرسال رمز التحقق'
                ], 403);
            }
            $phoneNumber = $contract->booking->car->owner->phone;
        }

        // التحقق من رقم الهاتف
        if (empty($phoneNumber)) {
            return response()->json([
                'status' => false,
                'message' => 'رقم الهاتف غير متوفر'
            ], 400);
        }

        // توليد OTP جديد
        // $newOtp = rand(1000, 9999); // لأغراض التطوير - في الإنتاج استخدم rand(100000, 999999)
        $newOtp = 00000; // لأغراض التطوير - في الإنتاج استخدم rand(100000, 999999)

        //   $twilioService = new SMSService();
        //  $userOtpSent = $twilioService->sendMessage($phoneNumber, $newOtp);

        // تحديث البيانات في الكاش بدلاً من الجلسة
        $cacheKey = 'contract_otp_' . $contract->id;
        $cachedOtp = Cache::get($cacheKey, [
            'user_otp' => 123456, // قيمة افتراضية للتطوير
            'owner_otp' => 123456, // قيمة افتراضية للتطوير
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15)
        ]);

        // تحديث OTP المناسب فقط
        $cachedOtp[$request->user_type . '_otp'] = $newOtp;
        Cache::put($cacheKey, $cachedOtp, now()->addMinutes(15));

        // إرسال OTP (معلق لأغراض التطوير)
        // $this->sendOtp($phoneNumber, $newOtp, $request->user_type);

        return response()->json([
            'status' => true,
            'message' => 'تم إعادة إرسال رمز التحقق',
            'user_type' => $request->user_type,
            'otp' => $newOtp // لأغراض التطوير فقط، يجب إزالة هذا في الإنتاج
        ]);
    }




    // دالة مساعدة للتحقق من اكتمال التحقق
    protected function checkCompleteVerification($contract, $cacheKey)
    {
        $contract->refresh(); // نضمن أننا نقرأ أحدث بيانات العقد

        if ($contract->otp_user == 'verified' && $contract->otp_renter == 'verified') {
            $contract->update(['status' => 'verified']);
            Cache::forget($cacheKey); // حذف بيانات OTP من الكاش
        }
    }



















    public function myBooking(Request $request)
    {
        try {
            $user = auth()->user();

            // بناء استعلام الحجوزات حسب الفلاتر
            $query = Order_Booking::where('user_id', $user->id);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('date_from', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_end')) {
                $query->whereDate('date_end', '<=', Carbon::parse($request->date_end));
            }

            if ($request->has('car_id')) {
                $query->where('car_id', $request->car_id);
            }

            // استخدام Pagination بدلاً من get()
            $perPage = $request->get('per_page', 2); // افتراضي 15 عنصر في الصفحة
            $bookings = $query->with([
                'car',
                'car.car_image',
                'car.owner',
                'user',
                'car.brand',
                'car.years',
                'car.model'
            ])->orderBy('date_from', 'desc')->paginate($perPage);

            // حساب الإحصائيات بدون التأثر بالفلاتر مثل status
            $baseStatsQuery = Order_Booking::where('user_id', $user->id);

            if ($request->has('date_from')) {
                $baseStatsQuery->whereDate('date_from', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_end')) {
                $baseStatsQuery->whereDate('date_end', '<=', Carbon::parse($request->date_end));
            }

            if ($request->has('car_id')) {
                $baseStatsQuery->where('car_id', $request->car_id);
            }

            // حساب meta
            $meta = [
                'total' => (clone $baseStatsQuery)->count(),
                'pending' => (clone $baseStatsQuery)->where('status', 'pending')->count(),
                'confirm' => (clone $baseStatsQuery)->where('status', 'confirm')->count(),
                'picked_up' => (clone $baseStatsQuery)->where('status', 'picked_up')->count(),
                'Returned' => (clone $baseStatsQuery)->where('status', 'Returned')->count(),
                'Completed' => (clone $baseStatsQuery)->where('status', 'Completed')->count(),
                'Canceled' => (clone $baseStatsQuery)->where('status', 'Canceled')->count(),
                'draft' => (clone $baseStatsQuery)->where('status', 'draft')->count(),

            ];

            // إرجاع النتائج مع الحفاظ على هيكل Pagination
            return response()->json([
                'status' => true,
                'data' => $bookings->items(),
                'meta' => $meta,
                'pagination' => [
                    'current_page' => $bookings->currentPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                    'last_page' => $bookings->lastPage(),
                    'from' => $bookings->firstItem(),
                    'to' => $bookings->lastItem(),
                    'first_page_url' => $bookings->url(1),
                    'last_page_url' => $bookings->url($bookings->lastPage()),
                    'next_page_url' => $bookings->nextPageUrl(),
                    'prev_page_url' => $bookings->previousPageUrl(),
                    'path' => $bookings->path(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user bookings: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب الحجوزات',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }



    public function my_order(Request $request)
    {
        try {
            $user = auth()->user();

            // جلب جميع سيارات المستخدم الحالي
            $myCarIds = Cars::where('owner_id', $user->id)->pluck('id');

            // استعلام الحجوزات حسب سيارات المستخدم
            $query = Order_Booking::whereIn('car_id', $myCarIds);

            // فلترة حسب حالة الحجز
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // فلترة حسب السعر
            if ($request->has('total_price')) {
                $query->where('total_price', $request->total_price);
            }

            // فلترة حسب المستأجر (user_id)
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // فلترة حسب car_id بشرط أن تكون من سيارات المالك فقط
            if ($request->has('car_id')) {
                if ($myCarIds->contains($request->car_id)) {
                    $query->where('car_id', $request->car_id);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'السيارة غير تابعة لك',
                    ], 403);
                }
            }

            // فلترة حسب تاريخ البداية
            if ($request->has('date_from')) {
                $query->whereDate('date_from', '>=', Carbon::parse($request->date_from));
            }

            // فلترة حسب تاريخ النهاية
            if ($request->has('date_end')) {
                $query->whereDate('date_end', '<=', Carbon::parse($request->date_end));
            }

            // استخدام Pagination بدلاً من get()
            $perPage = $request->get('per_page', 2); // افتراضي 15 عنصر في الصفحة
            $orders = $query->with([
                'car',
                'car.car_image',
                'car.owner',
                'user',
                'car.brand',
                'car.years',
                'car.model'
            ])->orderBy('date_from', 'desc')->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching owner orders: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب الطلبات',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }



    public function show($id)
    {
        $user = auth()->user();
        $booking = Order_Booking::with(['car_details', 'car_details.car_image', 'payment'])->find($id);

        if (!$booking || $booking->user_id != $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود.او غير تابع لك',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $booking,
        ]);
    }

    public function show_my_order($id)
    {
        $user = auth()->user();

        // Get all car IDs owned by the user
        $myCarIds = Cars::where('owner_id', $user->id)->pluck('id');

        // Find the booking with relationships
        $booking = Order_Booking::with([
            'car_details',
            'car_details.car_image',
            'user',
        ])
            ->find($id);

        // Check if booking exists and belongs to user's cars
        if (!$booking || !$myCarIds->contains($booking->car_id)) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود أو السيارة غير تابعة لك',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $booking,
        ]);
    }




    public function calendar($id)
    {
        $bookings = Order_Booking::where('car_id', $id)
            ->where('status', '!=', 'draft')
            ->whereDate('date_from', '>=', Carbon::today())
            ->get(['date_from', 'date_end', 'status']);

        $trips = [];

        foreach ($bookings as $booking) {
            $trips[] = [
                'start'  => Carbon::parse($booking->date_from)->toDateString(),
                'end'    => Carbon::parse($booking->date_end)->toDateString(),
                'status' => $booking->status,
            ];
        }

        return response()->json([
            'trips' => $trips
        ]);
    }


    public function get_all_filter(Request $request)
    {
        $user = auth()->user();

        // بناء استعلام الحجوزات حسب الفلاتر
        $query = Order_Booking::where('user_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('date_from', '>=', Carbon::parse($request->date_from));
        }

        if ($request->has('date_end')) {
            $query->whereDate('date_end', '<=', Carbon::parse($request->date_end));
        }

        if ($request->has('car_id')) {
            $query->where('car_id', $request->car_id);
        }

        // تنفيذ الاستعلام وجلب النتائج
        $bookings = $query->with([
            'car_details',
            'car_details.car_image',
        ])->orderBy('date_from', 'desc')->get();

        // حساب الإحصائيات بدون التأثر بالفلاتر مثل status
        $baseStatsQuery = Order_Booking::where('user_id', $user->id);

        if ($request->has('date_from')) {
            $baseStatsQuery->whereDate('date_from', '>=', Carbon::parse($request->date_from));
        }

        if ($request->has('date_end')) {
            $baseStatsQuery->whereDate('date_end', '<=', Carbon::parse($request->date_end));
        }

        if ($request->has('car_id')) {
            $baseStatsQuery->where('car_id', $request->car_id);
        }

        // حساب meta
        $meta = [
            'total' => (clone $baseStatsQuery)->count(),

            'pending' => (clone $baseStatsQuery)->where('status', 'pending')->count(),

            'picked_up' => (clone $baseStatsQuery)->where('status', 'picked_up')->count(),
            'Returned' => (clone $baseStatsQuery)->where('status', 'Returned')->count(),
            'Completed' => (clone $baseStatsQuery)->where('status', 'Completed')->count(),
            'Canceled' => (clone $baseStatsQuery)->where('status', 'Canceled')->count(),
        ];

        return response()->json([
            'status' => true,
            'data' => $bookings,
            'meta' => $meta,
        ]);
    }




    public function get_all_filter_admin(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Read-Bookings')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }

        try {
            // Start with a query builder instead of getting all results
            $query = Order_Booking::query();

            $user = Auth::user(); // الحصول على بيانات المستخدم الحالي

            // إذا كان المستخدم من النوع 2 (ممثل)، نضيف شرط has_representative == 0
            if ($user && $user->type == 2) {
                $query->where('has_representative', 0);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('date_from', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_end')) {
                $query->whereDate('date_end', '<=', Carbon::parse($request->date_end));
            }

            if ($request->has('car_id')) {
                $query->where('car_id', $request->car_id);
            }

            // استخدام Pagination بدلاً من get()
            $perPage = $request->get('per_page', 15); // افتراضي 15 عنصر في الصفحة
            $bookings = $query->with([
                'car',
                'car.car_image',
                'car.owner',
                'user',
                'car.brand',
                'car.years',
                'car.model'
            ])->orderBy('date_from', 'desc')->paginate($perPage);

            // For base stats, also start with a query builder
            $baseStatsQuery = Order_Booking::query();

            // تطبيق نفس شروط المستخدم على الإحصائيات
            if ($user && $user->type == 2) {
                $baseStatsQuery->where('has_representative', 0);
            }

            if ($request->has('date_from')) {
                $baseStatsQuery->whereDate('date_from', '>=', Carbon::parse($request->date_from));
            }

            if ($request->has('date_end')) {
                $baseStatsQuery->whereDate('date_end', '<=', Carbon::parse($request->date_end));
            }

            if ($request->has('car_id')) {
                $baseStatsQuery->where('car_id', $request->car_id);
            }

            // Calculate meta
            $meta = [
                'total' => $baseStatsQuery->count(),
                'pending' => (clone $baseStatsQuery)->where('status', 'pending')->count(),
                'picked_up' => (clone $baseStatsQuery)->where('status', 'picked_up')->count(),
                'confirm' => (clone $baseStatsQuery)->where('status', 'confirm')->count(),
                'draft' => (clone $baseStatsQuery)->where('status', 'draft')->count(),
                'Returned' => (clone $baseStatsQuery)->where('status', 'Returned')->count(),
                'Completed' => (clone $baseStatsQuery)->where('status', 'Completed')->count(),
                'Canceled' => (clone $baseStatsQuery)->where('status', 'Canceled')->count(),
            ];

            return response()->json([
                'status' => true,
                'data' => $bookings->items(),
                'meta' => $meta,
                'pagination' => [
                    'current_page' => $bookings->currentPage(),
                    'per_page' => $bookings->perPage(),
                    'total' => $bookings->total(),
                    'last_page' => $bookings->lastPage(),
                    'from' => $bookings->firstItem(),
                    'to' => $bookings->lastItem(),
                    'first_page_url' => $bookings->url(1),
                    'last_page_url' => $bookings->url($bookings->lastPage()),
                    'next_page_url' => $bookings->nextPageUrl(),
                    'prev_page_url' => $bookings->previousPageUrl(),
                    'path' => $bookings->path(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching admin filtered bookings: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب الحجوزات',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }


    public function change_status_admin(Request $request, $id)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('ChangeStatus-Booking')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }

        $user = auth()->user();
        $validate = Validator::make($request->all(), [
            'status' => 'required|in:pending,picked_up,Returned,confirm,Completed,Canceled'
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 400);
        }

        $booking = Order_Booking::find($id);

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود أو غير تابع لك',
            ], 404);
        }

        $newStatus = $request->status;
        $currentStatus = $booking->status;

        // الحالات الخاصة وتحقق الشروط
        switch ($newStatus) {
                case 'confirm':
                // if (!in_array($currentStatus, ['picked_up', 'Returned'])) {
                if ($currentStatus !== 'pending') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن قبول الحجز إلا إذا كانت الحالة pending',
                    ], 400);
                }
                $booking->status = 'confirm';
                $booking->save(); // حفظ التغييرات
                break;
            case 'picked_up':
                if ($currentStatus !== 'confirm') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى confirm إلا إذا كانت الحالة السابقة picked_up',
                    ], 400);
                }
                $booking->status = 'picked_up';
                break;

            case 'Returned':
                if ($currentStatus !== 'picked_up' || !$booking->is_paid) {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى picked_up إلا إذا كانت الحالة السابقة Returned وتم الدفع',
                    ], 400);
                }
                $booking->status = 'Returned';


                break;

            case 'Completed':
                if ($currentStatus !== 'Returned') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى Returned إلا إذا كانت الحالة السابقة Completed',
                    ], 400);
                }
                $booking->status = 'Completed';
                $booking->completed_at = now(); // استخدام الوقت الحالي
                $booking->save(); // حفظ التغييرات
                $Review = Review::create([
                    'user_id' => $user->id,
                    'car_id' => $booking->car_id,
                    'status' => 'pending',
                ]);
                break;

            case 'Canceled':
                // if (!in_array($currentStatus, ['picked_up', 'Returned'])) {
                if ($currentStatus !== 'pending' && $currentStatus !== 'confirm') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن إنهاء الحجز إلا إذا كانت الحالة pending  او draft',
                    ], 400);
                }
                $booking->status = 'Canceled';

                break;

            default:
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير مدعومة',
                ], 400);
        }

        $booking->save();

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الحجز بنجاح',
            'new_status' => $booking->status,
        ]);
    }


    public function change_status_renter(Request $request, $id)
    {

        $user = auth()->user();
        $validate = Validator::make($request->all(), [
            'status' => 'required|in:picked_up,Returned,Canceled'
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 400);
        }

        $booking = Order_Booking::find($id);

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود أو غير تابع لك',
            ], 404);
        }

        $newStatus = $request->status;
        $currentStatus = $booking->status;

        // الحالات الخاصة وتحقق الشروط
        switch ($newStatus) {


            case 'picked_up':
                if ($currentStatus !== 'confirm' || !$booking->is_paid) {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى picked_up إلا إذا كانت الحالة السابقة confirm وتم الدفع',
                    ], 400);
                }
                $booking->status = 'picked_up';


                break;

            case 'Returned':
                if ($currentStatus !== 'picked_up') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى Returned إلا إذا كانت الحالة السابقة picked_up',
                    ], 400);
                }
                $booking->status = 'Returned';
                break;

            case 'Canceled':
                if ($currentStatus !== 'pending' && $currentStatus !== 'confirm') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن إلغاء الحجز إلا إذا كان في حالة pending أو confirmed',
                    ], 400);
                }
                $booking->status = 'Canceled';
                break;

            default:
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير مدعومة',
                ], 400);
        }

        $booking->save();

        event(new \App\Events\PrivateNotificationEvent($booking, 'success', $user->id));
        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الحجز بنجاح',
            'new_status' => $booking->status,
        ]);
    }


    public function change_status_owner(Request $request, $id)
    {

        $user = auth()->user();


        $validate = Validator::make($request->all(), [
            'status' => 'required|in:Completed,Canceled,confirm'
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 400);
        }

        $booking = Order_Booking::find($id);


        $car = Cars::find($booking->car_id);

        if ($car->owner_id != $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود.او غير تابع لك',
            ], 404);
        }

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود أو غير تابع لك',
            ], 404);
        }

        $newStatus = $request->status;
        $currentStatus = $booking->status;

        // الحالات الخاصة وتحقق الشروط
        switch ($newStatus) {
            case 'confirm':
                // if (!in_array($currentStatus, ['picked_up', 'Returned'])) {
                if ($currentStatus !== 'pending') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن قبول الحجز إلا إذا كانت الحالة pending',
                    ], 400);
                }
                $booking->status = 'confirm';
                $booking->save(); // حفظ التغييرات
                break;
            case 'Completed':
                // if (!in_array($currentStatus, ['picked_up', 'Returned'])) {
                if ($currentStatus !== 'Returned') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن إنهاء الحجز إلا إذا كانت الحالة Returned',
                    ], 400);
                }
                $booking->status = 'Completed';
                $booking->completed_at = now(); // استخدام الوقت الحالي
                $booking->save(); // حفظ التغييرات
                $Review = Review::create([
                    'user_id' => $user->id,
                    'car_id' => $booking->car_id,
                    'status' => 'pending',

                ]);
                break;
            case 'Canceled':
                if ($currentStatus !== 'pending' && $currentStatus !== 'confirm') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى Canceled إلا إذا كانت الحالة السابقة pending  or draft ',
                    ], 400);
                }
                $booking->status = 'Canceled';
                $booking->status = 'Canceled';
                $booking->save(); // حفظ التغييرات

                break;
            default:
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير مدعومة',
                ], 400);
        }
        $booking->save();
        event(new \App\Events\PrivateNotificationEvent($booking, 'success', $user->id));
        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الحجز بنجاح',
            'new_status' => $booking->status,
        ]);
    }

    public function change_is_paid($id)
    {

        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('ChangePaid-Booking')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }

        $booking = Order_Booking::find($id);

        $booking->is_paid = 1;

        $booking->save();

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الحجز بنجاح',
            'new_status' => $booking->is_paid,
        ]);
    }

    /*  public function accept_order($order_booking_id)
    {
        // الحصول على طلب الحجز
        $order = Order_Booking::find($order_booking_id);

        // التحقق من وجود الطلب
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }
        try {

            // تحديث حالة الطلب إذا لزم الأمر (اختياري)
            $order->update([
                'status' => 'confirmed', // أو أي حالة أخرى تريدها

            ]);

            return response()->json([
                'status' => true,
                'message' => 'Order confirmed successfully',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to accept order: ' . $e->getMessage()
            ], 500);
        }
    }*/



    public function updateBooking(Request $request, $bookingId)
    {
        $user = auth()->user();

        // البحث عن الحجز المطلوب
        $booking = Order_Booking::find($bookingId);

        // التحقق من وجود الحجز
        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود'
            ], 404);
        }

        // التحقق من أن المستخدم هو صاحب الحجز
        if ($booking->user_id != $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بتعديل هذا الحجز'
            ], 403);
        }

        // التحقق من أن الحجز قابل للتعديل (في حالة pending فقط)
        if (!in_array($booking->status, ['draft', 'pending'])) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن تعديل الحجز في حالته الحالية. يمكن التعديل فقط في حالة "مسودة" أو "قيد الانتظار"'
            ], 422);
        }

        // قواعد التحقق
        $validationRules = [
            'date_from' => 'sometimes|required|date|after_or_equal:today',
            'date_end' => 'sometimes|required|date|after:date_from',
            'driver_type' => 'sometimes|required|string|in:pick_up,mail_in',
        ];

        // إضافة قواعد التحقق المشروطة
        if ($request->has('driver_type')) {
            if ($request->driver_type == 'pick_up') {
                $validationRules['station_id'] = 'required|exists:stations,id';
            } elseif ($request->driver_type == 'mail_in') {
                $validationRules['lat'] = 'required|numeric';
                $validationRules['lang'] = 'required|numeric';
            }
        }

        // التحقق من صحة البيانات
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في التحقق',
                'errors' => $validator->errors()
            ], 422);
        }

        // تحضير البيانات للتحديث
        $updateData = [];

        // التحقق من تواريخ الحجز الجديدة
        $dateFrom = $request->has('date_from') ? Carbon::parse($request->date_from) : Carbon::parse($booking->date_from);
        $dateEnd = $request->has('date_end') ? Carbon::parse($request->date_end) : Carbon::parse($booking->date_end);

        // التحقق من مدة الحجز
        $car = Cars::find($booking->car_id);
        $days = $dateFrom->diffInDays($dateEnd);

        if ($days < $car->min_day_trip) {
            return response()->json([
                'status' => false,
                'message' => 'مدة الحجز أقل من الحد الأدنى المسموح به: ' . $car->min_day_trip . ' أيام'
            ], 422);
        }

        if (!is_null($car->max_day_trip) && $days > $car->max_day_trip) {
            return response()->json([
                'status' => false,
                'message' => 'مدة الحجز تتجاوز الحد الأقصى المسموح به: ' . $car->max_day_trip . ' أيام'
            ], 422);
        }

        // التحقق من تعارض الحجوزات (استثناء الحجز الحالي)
        $existingBooking = Order_Booking::where('car_id', $booking->car_id)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', ['pending', 'picked_up', 'Returned'])
            ->where(function ($query) use ($dateFrom, $dateEnd) {
                $query->whereBetween('date_from', [$dateFrom, $dateEnd])
                    ->orWhereBetween('date_end', [$dateFrom, $dateEnd])
                    ->orWhere(function ($q) use ($dateFrom, $dateEnd) {
                        $q->where('date_from', '<', $dateFrom)
                            ->where('date_end', '>', $dateEnd);
                    });
            })
            ->exists();

        if ($existingBooking) {
            return response()->json([
                'status' => false,
                'message' => 'السيارة محجوزة بالفعل في الفترة المطلوبة'
            ], 422);
        }

        DB::beginTransaction();

        try {
            // إعداد بيانات التحديث
            if ($request->has('date_from')) {
                $updateData['date_from'] = $dateFrom;
            }

            if ($request->has('date_end')) {
                $updateData['date_end'] = $dateEnd;
            }

            if ($request->has('driver_type')) {
                $updateData['driver_type'] = $request->driver_type;

                if ($request->driver_type == 'pick_up') {
                    $updateData['station_id'] = $request->station_id;
                    $updateData['lat'] = null;
                    $updateData['lang'] = null;
                } elseif ($request->driver_type == 'mail_in') {
                    $updateData['lat'] = $request->lat;
                    $updateData['lang'] = $request->lang;
                    $updateData['station_id'] = null;
                }
            }

            if ($request->has('payment_method')) {
                $updateData['payment_method'] = $request->payment_method;
            }

            // حساب السعر الجديد إذا تغيرت التواريخ
            if ($request->has('date_from') || $request->has('date_end')) {
                $total_price = $car->price * $days;

                if ($booking->with_driver) {
                    $driver_price = Driver_Price::first();
                    if ($driver_price) {
                        $total_price += $driver_price->price;
                    }
                }

                $updateData['total_price'] = $total_price;
            }

            // تطبيق التحديثات
            $booking->update($updateData);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم تحديث الحجز بنجاح',
                'data' => $booking->fresh(['car', 'user'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'فشل تحديث الحجز',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
