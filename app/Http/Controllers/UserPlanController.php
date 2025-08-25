<?php

namespace App\Http\Controllers;

use App\Models\Payment_Plan;
use App\Models\Plan;
use App\Models\User_Plan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

use GuzzleHttp\Client;
class UserPlanController extends Controller
{

    public function index(Request $request)
    {
        $user = auth()->user();

        // Start with base query
        $query = $user->user_plan()->with(['plan']);

        // Apply filters if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_paid')) {
            $query->where('is_paid', $request->is_paid);
        }

        if ($request->has('date_from')) {
            $query->where('date_from', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date_end', '<=', $request->date_to);
        }

        // Paginate results
        $subscriptions = $query->paginate($request->per_page ?? 10);

        return response()->json([
            'status' => true,
            'data' => $subscriptions,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        // Check for existing active or pending subscriptions
        $existingActivePlan = $user->user_plan()
            ->whereIn('status', ['active'])
            ->first();
        if ($existingActivePlan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Cannot create new subscription. You already have an active subscription.',
                    'existing_plan' => [
                        'id' => $existingActivePlan->id,
                        'plan_name' => $existingActivePlan->plan->name ?? 'Unknown Plan',
                        'status' => $existingActivePlan->status,
                        'date_from' => $existingActivePlan->date_from,
                        'date_end' => $existingActivePlan->date_end
                    ]
                ], 400);
            }
        $validationRules = [
            'plan_id' => 'required|exists:plans,id',
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
            $plan = Plan::findOrFail($request->plan_id);

            // Create pending plan first
            $newUserPlan = $user->user_plan()->create([
                'plan_id' => $plan->id,
                'price' => $plan->price,
                'status' => 'pending',
                'is_paid' => 0,
                'car_limite' => $plan->car_limite,
                'date_from' => null,
                'date_end' => null,
                'remaining_cars' => $plan->car_limite
            ]);

            // Try to create MontyPay checkout session
            try {
                $total_price = $newUserPlan->price;

                // بيانات MontyPay
                $merchantKey = env('MONTYPAY_MERCHANT_KEY');
                $merchantPass = env('MONTYPAY_MERCHANT_PASSWORD');
                $apiEndpoint = env('MONTYPAY_API_ENDPOINT');

                // استخدام البيانات الحقيقية للطلب
                $orderNumber = (string)$newUserPlan->id;
                $orderAmount = number_format($total_price, 2, '.', '');
                $orderCurrency = "USD";
                $orderDescription = "user_plan_id  #" . $newUserPlan->id;

                // توليد الهاش
                $hashString = $orderNumber .
                            $orderAmount .
                            $orderCurrency .
                            $orderDescription .
                            $merchantPass;

                $hashString = strtoupper($hashString);
                $md5Hash = md5($hashString);
                $generatedHash = sha1($md5Hash);

                // بناء payload للدفع
                $paymentPayload = [
                    'merchant_key' => $merchantKey,
                    'operation' => 'purchase',
                    'success_url' => url("/api/payment/plan/success/{$newUserPlan->id}"),
                    'cancel_url' => url("/api/payment/plan/cancel/{$newUserPlan->id}"),
                    'callback_url' => url('/api/payment/plan/callback/' . $newUserPlan->id),
                    'hash' => $generatedHash,
                    'order' => [
                        'description' => $orderDescription,
                        'number' => $orderNumber,
                        'amount' => $orderAmount,
                        'currency' => $orderCurrency
                    ],
                    'customer' => [
                        'name' => $user->name,
                        'email' => $user->email
                    ],
                    'billing_address' => [
                        'country' => 'AE',
                        'city' => 'Dubai',
                        'address' => 'Dubai'
                    ]
                ];

                // إرسال طلب إنشاء جلسة دفع
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post($apiEndpoint, $paymentPayload);

                // في دالة store بعد نجاح الاستجابة
                if ($response->successful()) {
                    $paymentData = $response->json();

                    Log::info('MontyPay Response:', [
                        'status' => $response->status(),
                        'data' => $paymentData,
                        'booking_id' => $newUserPlan->id
                    ]);

                    // إنشاء سجل الدفع بدون transaction_id (لأنه غير موجود في الاستجابة الأولية)
                    Payment_Plan::create([
                        'user_plan_id' => $newUserPlan->id,
                        'user_id' => $user->id,
                        'payment_method' => 'montypay',
                        'amount' => $total_price,
                        'status' => 'pending',
                        'transaction_id' => null, // سيتم تعبئته لاحقاً عبر callback
                        'payment_details' => array_merge($paymentData, [
                            'montypay_redirect_url' => $paymentData['redirect_url'] ?? null,
                            'created_at' => now(),
                            'expecting_callback' => true // إضافة علامة أننا ننتجار callback
                        ])
                    ]);

                    DB::commit();

                    return response()->json([
                        'status' => true,
                        'message' => 'تم إنشاء الاشتراك بنجاح يرجى إتمام الدفع',
                        'data' => [
                            'subscribe' => $newUserPlan->load(['plan', 'user']),
                            'payment_url' => $paymentData['redirect_url'] ?? null,
                        ],
                    ], 201);
                } else {
                    // فشل في إنشاء جلسة الدفع
                    DB::rollBack();

                    return response()->json([
                        'status' => false,
                        'message' => 'فشل في إنشاء جلسة الدفع',
                        'error' => $response->json(),
                    ], 400);
                }
            } catch (\Exception $e) {
                // في حالة خطأ MontyPay
                Log::error('MontyPay error: '.$e->getMessage());
                DB::rollBack();

                return response()->json([
                    'status' => false,
                    'message' => 'فشل في الاتصال بخدمة الدفع',
                    'error' => $e->getMessage(),
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StoreSubscription failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating subscription.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function createPaymentForPendingPlan(Request $request)
    {
        $user = auth()->user();

        $validationRules = [
            'user_plan_id' => 'required|exists:user__plans,id',
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
            // البحث عن الاشتراك المعلّق وغير المدفوع
            $userPlan = User_Plan::where('id', $request->user_plan_id)
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'pending_renewal_active', 'pending_renewal_exp', 'pending_upgrade'])
                ->where('is_paid', 0)
                ->first();

            if (!$userPlan) {
                return response()->json([
                    'status' => false,
                    'message' => 'الاشتراك غير موجود أو تم دفعه مسبقاً أو ليس بحالة معلقة',
                ], 404);
            }

            // التحقق من عدم وجود عملية دفع معلقة لهذا الاشتراك
            $existingPayment = Payment_Plan::where('user_plan_id', $userPlan->id)
                ->where('status', 'pending')
                ->first();

            if ($existingPayment) {
                // إذا كان هناك عملية دفع معلقة، نعيد رابط الدفع الخاص بها
                $paymentUrl = $existingPayment->payment_details['montypay_redirect_url'] ?? null;

                if ($paymentUrl) {
                    return response()->json([
                        'status' => true,
                        'message' => 'يوجد رابط دفع معلق مسبقاً',
                        'data' => [
                            'subscribe' => $userPlan->load(['plan', 'user']),
                            'payment_url' => $paymentUrl,
                        ],
                    ], 200);
                }
            }

            // Try to create MontyPay checkout session
            $total_price = $userPlan->price;

            // بيانات MontyPay
            $merchantKey = env('MONTYPAY_MERCHANT_KEY');
            $merchantPass = env('MONTYPAY_MERCHANT_PASSWORD');
            $apiEndpoint = env('MONTYPAY_API_ENDPOINT');

            // استخدام البيانات الحقيقية للطلب
            $orderNumber = (string)$userPlan->id;
            $orderAmount = number_format($total_price, 2, '.', '');
            $orderCurrency = "USD";
            $orderDescription = "user_plan_id  #" . $userPlan->id;

            // توليد الهاش
            $hashString = $orderNumber .
                        $orderAmount .
                        $orderCurrency .
                        $orderDescription .
                        $merchantPass;

            $hashString = strtoupper($hashString);
            $md5Hash = md5($hashString);
            $generatedHash = sha1($md5Hash);

            // بناء payload للدفع
            $paymentPayload = [
                'merchant_key' => $merchantKey,
                'operation' => 'purchase',
                'success_url' => url("/api/payment/plan/success/{$userPlan->id}"),
                'cancel_url' => url("/api/payment/plan/cancel/{$userPlan->id}"),
                'callback_url' => url('/api/payment/plan/callback/' . $userPlan->id),
                'hash' => $generatedHash,
                'order' => [
                    'description' => $orderDescription,
                    'number' => $orderNumber,
                    'amount' => $orderAmount,
                    'currency' => $orderCurrency
                ],
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email
                ],
                'billing_address' => [
                    'country' => 'AE',
                    'city' => 'Dubai',
                    'address' => 'Dubai'
                ]
            ];

            // إرسال طلب إنشاء جلسة دفع
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post($apiEndpoint, $paymentPayload);

            // بعد نجاح الاستجابة
            if ($response->successful()) {
                $paymentData = $response->json();

                Log::info('MontyPay Response for existing plan:', [
                    'status' => $response->status(),
                    'data' => $paymentData,
                    'user_plan_id' => $userPlan->id
                ]);

                // إنشاء سجل الدفع
                Payment_Plan::create([
                    'user_plan_id' => $userPlan->id,
                    'user_id' => $user->id,
                    'payment_method' => 'montypay',
                    'amount' => $total_price,
                    'status' => 'pending',
                    'transaction_id' => null, // سيتم تعبئته لاحقاً عبر callback
                    'payment_details' => array_merge($paymentData, [
                        'montypay_redirect_url' => $paymentData['redirect_url'] ?? null,
                        'created_at' => now(),
                        'expecting_callback' => true
                    ])
                ]);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'تم إنشاء رابط الدفع بنجاح',
                    'data' => [
                        'subscribe' => $userPlan->load(['plan', 'user']),
                        'payment_url' => $paymentData['redirect_url'] ?? null,
                    ],
                ], 200);
            } else {
                // فشل في إنشاء جلسة الدفع
                DB::rollBack();

                Log::error('MontyPay failed for existing plan: ' . $userPlan->id, [
                    'response' => $response->json(),
                    'status' => $response->status()
                ]);

                return response()->json([
                    'status' => false,
                    'message' => 'فشل في إنشاء جلسة الدفع',
                    'error' => $response->json(),
                ], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create payment for pending plan failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء إنشاء رابط الدفع',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function hasActiveSubscription()
    {
        $hasSubscription = auth()->user()
            ->user_plan()
            ->where('status', 'active')
            ->exists();

        return response()->json([
            'has_active_subscription' => $hasSubscription
        ]);
    }


    public function adminIndex(Request $request)
    {
        // Verify admin access

        // Start with base query
        $query = User_Plan::with(['user', 'plan']);

        // Apply filters if provided
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_paid')) {
            $query->where('is_paid', $request->is_paid);
        }

        if ($request->has('date_from')) {
            $query->where('date_from', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date_end', '<=', $request->date_to);
        }

        // Paginate results
        $subscriptions = $query->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => true,
            'data' => $subscriptions,
        ]);
    }

     public function adminActivateSubscription( $user_plan_id)
    {

        DB::beginTransaction();

        try {
            // Find and update the user plan
            $userPlan = User_Plan::findOrFail($user_plan_id);

            $userPlan->update([
                'status' => 'active',
                'is_paid' => 1, // Set to 0 as per your requirement
                'date_from' => now(),
                'date_end' => now()->addDays($userPlan->plan->count_day)
            ]);

            // Update all pending cars for this user
            $updatedCarsCount = \App\Models\Cars::where('owner_id', $userPlan->user_id)
                ->where('is_paid', 0)
                ->where('status', 'pending')
                ->update(['is_paid' => 1]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Subscription manually activated successfully',
                'data' => [
                    'user_plan' => $userPlan,
                    'updated_cars_count' => $updatedCarsCount
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin activate subscription failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to activate subscription',
                'error' => $e->getMessage()
            ], 500);
        }
    }




    public function handleRenewOrUpgrade(Request $request, $userPlanId)
    {
        $user = auth()->user();

        // التحقق من صحة البيانات المدخلة
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:renew,upgrade',
            'plan_id' => 'required_if:type,upgrade|exists:plans,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // البحث عن خطة المستخدم الحالية
        $currentUserPlan = User_Plan::where('id', $userPlanId)
            ->where('user_id', $user->id)
            ->with('plan')
            ->first();

        if (!$currentUserPlan) {
            return response()->json([
                'status' => false,
                'message' => 'خطأ في العثور على الاشتراك الحالي',
            ], 404);
        }


        if (!in_array($currentUserPlan->status, ['active', 'expired'])) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن تجديد الاشتراك إلا إذا كان فعالاً أو منتهياً',
                'current_status' => $currentUserPlan->status
            ], 400);
        }

        DB::beginTransaction();

        try {
            $type = $request->type;

            if ($type === 'renew') {
                // تجديد الباقة الحالية
                if ($currentUserPlan->status === 'active') {
                    // إذا كانت الباقة نشطة، نضيف إلى القيم الحالية
                    $newRemainingCars = $currentUserPlan->remaining_cars + $currentUserPlan->plan->car_limite;
                    $newPrice = $currentUserPlan->plan->price;

                    // حفظ البيانات اللازمة للحسابات المستقبلية
                    $renewalData = [
                        'is_active_renewal' => true,
                        'original_remaining_cars' => $currentUserPlan->remaining_cars,
                        'cars_to_add' => $currentUserPlan->plan->car_limite,
                        'original_date_end' => Carbon::parse($currentUserPlan->date_end)->format('Y-m-d H:i:s'),
                        'days_to_add' => $currentUserPlan->plan->count_day
                    ];

                    // تحديث خطة المستخدم الحالية
                    $currentUserPlan->update([
                        'price' => $newPrice,
                        'remaining_cars' => $newRemainingCars,
                        'is_paid' => 0,
                        'status' => 'pending_renewal_active',

                        'renewal_data' => $renewalData // حفظ البيانات هنا
                    ]);

                } elseif ($currentUserPlan->status === 'expired') {
                    // إذا كانت الباقة منتهية، ننشئ باقة جديدة بنفس الخطة
                    $currentUserPlan->update([
                        'plan_id' => $currentUserPlan->plan_id,
                        'remaining_cars' => $currentUserPlan->plan->car_limite,
                        'status' => 'pending_renewal_exp',
                        'is_paid' => 0,
                        'price' => $currentUserPlan->plan->price // تأكد من تحديث السعر
                    ]);

                } else {
                    throw new \Exception('لا يمكن تجديد الاشتراك في حالته الحالية');
                }

            } else {
                // الترقية إلى باقة جديدة
                $newPlan = Plan::findOrFail($request->plan_id);

                // إنشاء باقة جديدة مع الخطة الجديدة
                $newUserPlan = User_Plan::create([
                    'user_id' => $user->id,
                    'plan_id' => $newPlan->id,
                    'price' => $newPlan->price,
                    'status' => 'pending_upgrade',
                    'is_paid' => 0,
                    'car_limite' => $newPlan->car_limite,
                    'date_from' => null,
                    'date_end' => null,
                    'remaining_cars' => $newPlan->car_limite,
                ]);

                // تحديث الباقة القديمة لتكون upgraded
                $currentUserPlan->update([
                    'status' => 'finished',
                ]);

                $currentUserPlan = $newUserPlan; // استخدام الباقة الجديدة للمعالجة
            }

            // إنشاء سجل دفع معلق
            $newPayment = Payment_Plan::create([
                'user_plan_id' => $currentUserPlan->id,
                'user_id' => $user->id,
                'payment_method' => 'montypay',
                'amount' => $currentUserPlan->price,
                'status' => 'pending',
                'transaction_id' => null,
                'payment_details' => [
                    'type' => $type,
                    'created_at' => now(),
                    'expecting_callback' => true
                ]
            ]);

            // محاولة إنشاء جلسة دفع مع MontyPay
            try {
                $merchantKey = env('MONTYPAY_MERCHANT_KEY');
                $merchantPass = env('MONTYPAY_MERCHANT_PASSWORD');
                $apiEndpoint = env('MONTYPAY_API_ENDPOINT');

                $orderNumber = (string)$currentUserPlan->id;
                $orderAmount = number_format($currentUserPlan->price, 2, '.', '');
                $orderCurrency = "USD";
                $orderDescription = "{$type} user_plan_id #" . $currentUserPlan->id;

                $hashString = $orderNumber . $orderAmount . $orderCurrency . $orderDescription . $merchantPass;
                $hashString = strtoupper($hashString);
                $md5Hash = md5($hashString);
                $generatedHash = sha1($md5Hash);

                $paymentPayload = [
                    'merchant_key' => $merchantKey,
                    'operation' => 'purchase',
                    'success_url' => url("/api/payment/plan/renew-upgrade-success/{$currentUserPlan->id}"),
                    'cancel_url' => url("/api/payment/plan/cancel/{$currentUserPlan->id}"),
                    'callback_url' => url('/api/payment/plan/callback/' . $currentUserPlan->id),
                    'hash' => $generatedHash,
                    'order' => [
                        'description' => $orderDescription,
                        'number' => $orderNumber,
                        'amount' => $orderAmount,
                        'currency' => $orderCurrency
                    ],
                    'customer' => [
                        'name' => $user->name,
                        'email' => $user->email
                    ],
                    'billing_address' => [
                        'country' => 'AE',
                        'city' => 'Dubai',
                        'address' => 'Dubai'
                    ]
                ];

                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post($apiEndpoint, $paymentPayload);

                if ($response->successful()) {
                    $paymentData = $response->json();

                    Log::info('MontyPay Response for renewal/upgrade:', [
                        'status' => $response->status(),
                        'data' => $paymentData,
                        'user_plan_id' => $currentUserPlan->id
                    ]);

                    // تحديث سجل الدفع برابط التوجيه
                    $newPayment->update([
                        'payment_details' => array_merge(
                            $newPayment->payment_details,
                            $paymentData,
                            ['montypay_redirect_url' => $paymentData['redirect_url'] ?? null]
                        )
                    ]);

                    DB::commit();

                    return response()->json([
                        'status' => true,
                        'message' => "تم طلب {$type} الاشتراك بنجاح يرجى إتمام الدفع",
                        'data' => [
                            'user_plan' => $currentUserPlan->load(['plan', 'user']),
                            'payment_url' => $paymentData['redirect_url'] ?? null,
                            'type' => $type
                        ],
                    ], 201);
                } else {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'فشل في إنشاء جلسة الدفع',
                        'error' => $response->json(),
                    ], 400);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('MontyPay error in renew/upgrade: '.$e->getMessage());

                return response()->json([
                    'status' => false,
                    'message' => 'فشل في الاتصال بخدمة الدفع',
                    'error' => $e->getMessage(),
                ], 500);
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('RenewOrUpgrade failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An error occurred while processing your request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }











}
