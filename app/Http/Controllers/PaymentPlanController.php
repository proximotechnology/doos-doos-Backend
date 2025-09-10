<?php

namespace App\Http\Controllers;

use App\Models\Cars;
use App\Models\Payment_Plan;
use App\Models\User_Plan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http; // أضف هذا السطر

class PaymentPlanController extends Controller
{
    public function success($bookingId)
    {
        try {
            DB::beginTransaction();

            // البحث عن الحجز مع العلاقات
            $booking = User_Plan::with(['user', 'plan'])->findOrFail($bookingId);

            // الحصول على query parameters من الURL
            $request = request();
            $transId = $request->query('trans_id');
            $paymentId = $request->query('payment_id');


            // تحديث حالة الدفع والحجز
            $booking->update([
                'is_paid' => 1,
                'status' => 'active',
                'date_from' => now(),
                'date_end' => Carbon::now()->addDays($booking->plan->count_day ?? 30)->format('Y-m-d H:i:s')
            ]);

            // إذا كان لدى المستخدم سيارات نشطة بدون خطة، نربطها بهذه الخطة ونخصمها من remaining_cars

            // البحث عن سجل الدفع وتحديثه
            $payment = Payment_Plan::where('user_plan_id', $bookingId)->first();

            $paymentDetails = [
                'success_callback_received_at' => now(),
                'montypay_payment_id' => $paymentId,
                'montypay_trans_id' => $transId,
                'query_params' => $request->query(),

                'remaining_cars_after' => $booking->remaining_cars
            ];

            if ($payment) {
                $existingDetails = $payment->payment_details ?? [];

                // تحديث سجل الدفع
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'transaction_id' => $transId,
                    'payment_details' => array_merge($existingDetails, $paymentDetails)
                ]);
            } else {
                // إذا لم يكن هناك سجل دفع، إنشاء واحد جديد
                $payment = Payment_Plan::create([
                    'user_plan_id' => $bookingId,
                    'user_id' => $booking->user_id,
                    'payment_method' => 'montypay',
                    'amount' => $booking->price,
                    'status' => 'completed',
                    'transaction_id' => $transId,
                    'paid_at' => now(),
                    'payment_details' => array_merge([
                        'created_via' => 'success_url',
                        'created_at' => now()
                    ], $paymentDetails)
                ]);
            }

            DB::commit();

            // جلب السيارات المرتبطة حديثاً لإرجاعها في الresponse
            $linkedCars = Cars::where('user_plan_id', $bookingId)
                ->where('status', 'active')
                ->get();
            $frontendSuccessUrl = $booking->frontend_success_url;

            if ($frontendSuccessUrl) {
                        // إضافة parameters إلى رابط Frontend
                        $redirectUrl = $this->buildFrontendRedirectUrl($frontendSuccessUrl, [
                            'booking_id' => $booking->id,
                            'status' => 'success',
                            'transaction_id' => $payment->transaction_id,
                            'paid_at' => now()->toISOString(),
                            'amount' => $booking->total_price
                        ]);

                        // التحويل إلى رابط Frontend
                        return redirect()->away($redirectUrl);
            }
            // إرجاع رد JSON مع معلومات الحجز والسيارات المرتبطة
            return response()->json([
                'status' => true,
                'message' => 'Payment completed successfully' ,
                'data' => [
                    'subscribe' => $booking->fresh(), // إعادة تحميل البيانات مع التحديثات
                    'payment_status' => 'completed',
                    'paid_at' => now()->toDateTimeString(),
                    'transaction_id' => $payment->transaction_id,
                    'remaining_cars' => $booking->remaining_cars,
                    'linked_cars' => $linkedCars
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing successful payment: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to process payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
/**
 * معالجة حالة إلغاء الدفع
 */
    public function cancel($bookingId)
    {
        try {
            // البحث عن الحجز مع العلاقات
            $booking = User_Plan::with(['plan', 'user'])->findOrFail($bookingId);
                        // تحديث حالة الدفع والحجز
            $booking->update([
                'status' => 'active',
            ]);

            // تحديث سجل الدفع إذا كان موجوداً
            $payment = Payment_Plan::where('user_plan_id', $bookingId)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'payment_details' => array_merge(
                        $payment->payment_details ?? [],
                        ['cancelled_at' => now()]
                    )
                ]);
            }

            Log::info('Payment cancelled for user_subscribe: ' . $bookingId);

            // إرجاع رد JSON مع معلومات الإلغاء
            return response()->json([
                'status' => true,
                'message' => 'Payment cancelled successfully',
                'data' => [
                    'booking' => $booking,
                    'payment_status' => 'cancelled',
                    'cancelled_at' => now()->toDateTimeString()
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error processing payment cancellation: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to process payment cancellation',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function callback($bookingId, Request $request)
    {
        // تسجيل كامل تفاصيل الطلب الوارد
        Log::info('MontyPay Callback Received', [
            'booking_id' => $bookingId,
            'method' => $request->method(),
            'full_url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'input_data' => $request->all(),
            'query_params' => $request->query(),
            'json_data' => $request->json()->all(),
        ]);

        DB::beginTransaction();

        try {
            // البحث عن الاشتراك
            $booking = User_Plan::find($bookingId);
            if (!$booking) {
                Log::error('Booking not found: ' . $bookingId);
                return response()->json(['status' => 'error', 'message' => 'Booking not found'], 404);
            }

            // البحث عن سجل الدفع
            $payment = Payment_Plan::where('user_plan_id', $bookingId)->first();
            if (!$payment) {
                Log::error('Payment record not found for booking: ' . $bookingId);
                return response()->json(['status' => 'error', 'message' => 'Payment record not found'], 404);
            }

            // استخراج transaction_id من البيانات (trans_id هو الحقل الصحيح في MontyPay)
            $transactionId = $request->input('trans_id')
                            ?? $request->query('trans_id')
                            ?? $request->input('transaction_id')
                            ?? null;

            Log::info('Extracted transaction data from callback:', [
                'trans_id' => $request->input('trans_id'),
                'transaction_id' => $transactionId,
                'all_input' => $request->all()
            ]);

            // تحديث سجل الدفع ببيانات الـ callback
            $payment->update([
                'transaction_id' => $transactionId,
                'status' => 'completed',
                'paid_at' => now(),
                'payment_details' => array_merge(
                    $payment->payment_details ?? [],
                    $request->all(),
                    [
                        'callback_received_at' => now(),
                        'montypay_payment_id' => $request->input('payment_id'),
                        'montypay_trans_id' => $request->input('trans_id'),
                        'montypay_order_id' => $request->input('order_id'),
                        'montypay_hash' => $request->input('hash')
                    ]
                )
            ]);

            // تحديث الاشتراك
            $booking->update([
                'is_paid' => 1,
                'status' => 'active',
                'date_from' => now(),
                'date_end' => now()->addDays($booking->plan->count_day ?? 30)
            ]);

            DB::commit();

            Log::info('Payment completed via callback for booking: ' . $bookingId, [
                'transaction_id' => $transactionId,
                'montypay_trans_id' => $request->input('trans_id'),
                'montypay_payment_id' => $request->input('payment_id')
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Callback processing error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }









    public function renewUpgradeSuccess($bookingId)
    {
        try {
            DB::beginTransaction();

            // البحث عن الحجز مع العلاقات
            $booking = User_Plan::with(['user', 'plan', 'cars'])->findOrFail($bookingId);


            // الحصول على query parameters من الURL
            $request = request();
            $transId = $request->query('trans_id');
            $paymentId = $request->query('payment_id');

            // تحديث حالة الدفع والحجز
            $booking->update([
                'is_paid' => 1,
                'status' => 'active',
                'date_from' => now(),
                'date_end' => Carbon::parse($booking->date_end ?? now())->addDays($booking->plan->count_day ?? 30)->format('Y-m-d H:i:s')           ]);

            // التصحيح: استخدام اسم العمود الصحيح user_plan_id (بشرطة واحدة)
            $affectedCars = $booking->cars()
                ->where('status', 'expired') // فقط السيارات المنتهية
                ->update([
                    'status' => 'active',
                    'user_plan_id' => $bookingId // تأكيد تعيين user_plan_id
                ]);

            // البحث عن سجل الدفع وتحديثه
            $payment = Payment_Plan::where('user_plan_id', $bookingId)->first();

            if ($payment) {
                $paymentDetails = $payment->payment_details ?? [];

                // تحديث سجل الدفع
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'transaction_id' => $transId,
                    'payment_details' => array_merge($paymentDetails, [
                        'success_callback_received_at' => now(),
                        'montypay_payment_id' => $paymentId,
                        'montypay_trans_id' => $transId,
                        'query_params' => $request->query(),
                        'activated_cars_count' => $affectedCars
                    ])
                ]);
            } else {
                // إذا لم يكن هناك سجل دفع، إنشاء واحد جديد
                $payment = Payment_Plan::create([
                    'user_plan_id' => $bookingId,
                    'user_id' => $booking->user_id,
                    'payment_method' => 'montypay',
                    'amount' => $booking->price,
                    'status' => 'completed',
                    'transaction_id' => $transId,
                    'paid_at' => now(),
                    'payment_details' => [
                        'created_via' => 'success_url',
                        'created_at' => now(),
                        'montypay_payment_id' => $paymentId,
                        'montypay_trans_id' => $transId,
                        'query_params' => $request->query(),
                        'activated_cars_count' => $affectedCars
                    ]
                ]);
            }

            Log::info('Payment successful via success URL for booking: ' . $bookingId, [
                'booking_id' => $bookingId,
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'activated_cars' => $affectedCars
            ]);

            DB::commit();

            // التصحيح: استخدام اسم العمود الصحيح هنا أيضاً
            $activatedCars = $booking->cars()
                ->where('status', 'active')
                ->get();


             $frontendSuccessUrl = $booking->frontend_success_url;

            if ($frontendSuccessUrl) {
                    // إضافة parameters إلى رابط Frontend
                    $redirectUrl = $this->buildFrontendRedirectUrl($frontendSuccessUrl, [
                        'booking_id' => $booking->id,
                        'status' => 'success',
                        'transaction_id' => $payment->transaction_id,
                        'paid_at' => now()->toISOString(),
                        'amount' => $booking->total_price
                    ]);

                    // التحويل إلى رابط Frontend
                    return redirect()->away($redirectUrl);
            }
            return response()->json([
                'status' => true,
                'message' => 'Payment completed successfully and cars activated',
                'data' => [
                    'subscribe' => $booking,
                    'payment_status' => 'completed',
                    'paid_at' => now()->toDateTimeString(),
                    'transaction_id' => $payment->transaction_id,
                    'activated_cars_count' => $affectedCars,
                    'activated_cars' => $activatedCars
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing successful payment: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to process payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }



        private function buildFrontendRedirectUrl($baseUrl, $params)
    {
        $queryString = http_build_query($params);
        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . $queryString;
    }
}

































/**public function success($bookingId)
{
    try {
        DB::beginTransaction();

        // البحث عن الحجز مع العلاقات
        $booking = User_Plan::with(['user', 'plan'])->findOrFail($bookingId);

        // الحصول على query parameters من الURL
        $request = request();
        $transId = $request->query('trans_id');
        $paymentId = $request->query('payment_id');

        Log::info('Success URL called with params:', [
            'booking_id' => $bookingId,
            'query_params' => $request->query(),
            'trans_id' => $transId,
            'payment_id' => $paymentId
        ]);

        // تحديث حالة الدفع والحجز
        $booking->update([
            'is_paid' => 1,
            'status' => 'active',
            'date_from' => now(),
            'date_end' => now()->addDays($booking->plan->count_day ?? 30)
        ]);

        // البحث عن جميع السيارات النشطة للمستخدم التي ليس لها user_plan_id
        $activeCarsWithoutPlan = Cars::where('owner_id', $booking->user_id)
            ->where('status', 'active')
            ->whereNull('user_plan_id')
            ->get();

        $activeCarsCount = $activeCarsWithoutPlan->count();

        // إذا كان لدى المستخدم سيارات نشطة بدون خطة، نربطها بهذه الخطة ونخصمها من remaining_cars
        if ($activeCarsCount > 0) {
            // حساب عدد السيارات التي يمكن إضافتها ضمن الحد المسموح
            $carsToAdd = min($activeCarsCount, $booking->plan->car_limite);

            // ربط السيارات بالاشتراك
            Cars::where('owner_id', $booking->user_id)
                ->where('status', 'active')
                ->whereNull('user_plan_id')
                ->limit($carsToAdd)
                ->update(['user_plan_id' => $bookingId]);

            // تحديث remaining_cars مع الخصم
            $newRemainingCars = max(0, $booking->plan->car_limite - $carsToAdd);

            $booking->update([
                'remaining_cars' => $newRemainingCars
            ]);

            Log::info('Linked active cars to subscription:', [
                'subscription_id' => $bookingId,
                'user_id' => $booking->user_id,
                'cars_linked' => $carsToAdd,
                'remaining_cars' => $newRemainingCars
            ]);
        }

        // البحث عن سجل الدفع وتحديثه
        $payment = Payment_Plan::where('user_plan_id', $bookingId)->first();

        $paymentDetails = [
            'success_callback_received_at' => now(),
            'montypay_payment_id' => $paymentId,
            'montypay_trans_id' => $transId,
            'query_params' => $request->query(),
            'active_cars_linked' => $activeCarsCount,
            'cars_added_to_plan' => $activeCarsCount > 0 ? $carsToAdd : 0,
            'remaining_cars_after' => $booking->remaining_cars
        ];

        if ($payment) {
            $existingDetails = $payment->payment_details ?? [];

            // تحديث سجل الدفع
            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'transaction_id' => $transId,
                'payment_details' => array_merge($existingDetails, $paymentDetails)
            ]);
        } else {
            // إذا لم يكن هناك سجل دفع، إنشاء واحد جديد
            $payment = Payment_Plan::create([
                'user_plan_id' => $bookingId,
                'user_id' => $booking->user_id,
                'payment_method' => 'montypay',
                'amount' => $booking->price,
                'status' => 'completed',
                'transaction_id' => $transId,
                'paid_at' => now(),
                'payment_details' => array_merge([
                    'created_via' => 'success_url',
                    'created_at' => now()
                ], $paymentDetails)
            ]);
        }

        Log::info('Payment successful via success URL for booking: ' . $bookingId, [
            'booking_id' => $bookingId,
            'payment_id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'active_cars_linked' => $activeCarsCount,
            'remaining_cars' => $booking->remaining_cars
        ]);

        DB::commit();

        // جلب السيارات المرتبطة حديثاً لإرجاعها في الresponse
        $linkedCars = Cars::where('user_plan_id', $bookingId)
            ->where('status', 'active')
            ->get();

        // إرجاع رد JSON مع معلومات الحجز والسيارات المرتبطة
        return response()->json([
            'status' => true,
            'message' => 'Payment completed successfully' .
                        ($activeCarsCount > 0 ? ' and active cars linked to subscription' : ''),
            'data' => [
                'subscribe' => $booking->fresh(), // إعادة تحميل البيانات مع التحديثات
                'payment_status' => 'completed',
                'paid_at' => now()->toDateTimeString(),
                'transaction_id' => $payment->transaction_id,
                'active_cars_linked' => $activeCarsCount,
                'remaining_cars' => $booking->remaining_cars,
                'linked_cars' => $linkedCars
            ]
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Error processing successful payment: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Failed to process payment',
            'error' => $e->getMessage()
        ], 500);
    }
} */
