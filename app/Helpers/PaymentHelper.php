<?php

namespace App\Helpers;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentHelper
{
    /**
     * إنشاء جلسة دفع MontyPay
     */
    public static function createMontyPaySession($booking, $user)
    {
        // بيانات MontyPay
        $merchantKey = env('MONTYPAY_MERCHANT_KEY');
        $merchantPass = env('MONTYPAY_MERCHANT_PASSWORD');
        $apiEndpoint = env('MONTYPAY_API_ENDPOINT');

        // استخدام البيانات الحقيقية للطلب
        $orderNumber = (string)$booking->id;
        $orderAmount = number_format($booking->total_price, 2, '.', '');
        $orderCurrency = "USD";
        $orderDescription = "Car Booking #" . $booking->id;

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
            'success_url' => url("/api/payment/success/{$booking->id}"),
            'cancel_url' => url("/api/payment/cancel/{$booking->id}"),
            'callback_url' => url('/api/payment/callback/' . $booking->id),
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

        if ($response->successful()) {
            $paymentData = $response->json();

            Log::info('MontyPay Response:', [
                'status' => $response->status(),
                'data' => $paymentData,
                'booking_id' => $booking->id
            ]);

            return [
                'success' => true,
                'data' => $paymentData
            ];
        } else {
            Log::error('MontyPay failed for booking: ' . $booking->id, [
                'response' => $response->json(),
                'status' => $response->status()
            ]);

            return [
                'success' => false,
                'error' => $response->json()
            ];
        }
    }

    /**
     * إنشاء سجل الدفع في قاعدة البيانات
     */
    public static function createPaymentRecord($booking, $user, $paymentData)
    {
        return Payment::create([
            'order_booking_id' => $booking->id,
            'user_id' => $user->id,
            'payment_method' => 'montypay',
            'amount' => $booking->total_price,
            'status' => 'pending',
            'transaction_id' => null,
            'payment_details' => array_merge($paymentData, [
                'montypay_redirect_url' => $paymentData['redirect_url'] ?? null,
                'created_at' => now(),
                'expecting_callback' => true
            ])
        ]);
    }

    /**
     * التحقق من وجود عملية دفع معلقة
     */
    public static function hasPendingPayment($bookingId)
    {
        $payment = Payment::where('order_booking_id', $bookingId)
            ->where('status', 'pending')
            ->first();

        if ($payment && isset($payment->payment_details['montypay_redirect_url'])) {
            return $payment->payment_details['montypay_redirect_url'];
        }

        return false;
    }
}
