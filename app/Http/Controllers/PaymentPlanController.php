<?php

namespace App\Http\Controllers;

use App\Models\Payment_Plan;
use App\Models\User_Plan;
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

            // البحث عن سجل الدفع وتحديثه
            $payment = Payment_Plan::where('user_plan_id', $bookingId)->first();

            if ($payment) {
                $paymentDetails = $payment->payment_details ?? [];

                // تحديث سجل الدفع
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'transaction_id' => $transId, // حفظ trans_id من query string
                    'payment_details' => array_merge($paymentDetails, [
                        'success_callback_received_at' => now(),
                        'montypay_payment_id' => $paymentId,
                        'montypay_trans_id' => $transId,
                        'query_params' => $request->query()
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
                    'transaction_id' => $transId, // حفظ trans_id من query string
                    'paid_at' => now(),
                    'payment_details' => [
                        'created_via' => 'success_url',
                        'created_at' => now(),
                        'montypay_payment_id' => $paymentId,
                        'montypay_trans_id' => $transId,
                        'query_params' => $request->query()
                    ]
                ]);
            }

            Log::info('Payment successful via success URL for booking: ' . $bookingId, [
                'booking_id' => $bookingId,
                'payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id
            ]);

            DB::commit();

            // إرجاع رد JSON مع معلومات الحجز
            return response()->json([
                'status' => true,
                'message' => 'Payment completed successfully',
                'data' => [
                    'subscribe' => $booking,
                    'payment_status' => 'completed',
                    'paid_at' => now()->toDateTimeString(),
                    'transaction_id' => $payment->transaction_id
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
            $booking->update([
                'status'  => 'faild'
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
}
