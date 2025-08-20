<?php

namespace App\Http\Controllers;

use App\Models\Order_Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\UserPaymentToken;
use Illuminate\Support\Facades\Http;

class PaymentController extends Controller
{
    public function success($bookingId)
    {
        try {
            // البحث عن الحجز مع العلاقات
            $booking = Order_Booking::with(['car', 'user', 'car.owner'])->findOrFail($bookingId);

            // تحديث حالة الدفع والحجز
            $booking->update([
                'is_paid' => 1,
            ]);

            // تحديث سجل الدفع إذا كان موجوداً
            $payment = Payment::where('order_booking_id', $bookingId)->first();
            if ($payment) {
                $payment->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'payment_details' => array_merge(
                        $payment->payment_details ?? [],
                        ['success_callback_received_at' => now()]
                    )
                ]);
            }

            Log::info('Payment successful for booking: ' . $bookingId);

            // إرجاع رد JSON مع معلومات الحجز
            return response()->json([
                'status' => true,
                'message' => 'Payment completed successfully',
                'data' => [
                    'booking' => $booking,
                    'payment_status' => 'completed',
                    'paid_at' => now()->toDateTimeString()
                ]
            ], 200);

        } catch (\Exception $e) {
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
        $booking = Order_Booking::with(['car', 'user'])->findOrFail($bookingId);

        // تحديث سجل الدفع إذا كان موجوداً
        $payment = Payment::where('order_booking_id', $bookingId)->first();
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

        Log::info('Payment cancelled for booking: ' . $bookingId);

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


}
