<?php

namespace App\Helpers;

use App\Models\Payment_Plan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentPlanHelper
{
    /**
     * إنشاء جلسة دفع MontyPay للخطط
     */
    public static function createMontyPaySessionForPlan($userPlan, $user)
    {
        // بيانات MontyPay
        $merchantKey = env('MONTYPAY_MERCHANT_KEY');
        $merchantPass = env('MONTYPAY_MERCHANT_PASSWORD');
        $apiEndpoint = env('MONTYPAY_API_ENDPOINT');

        // استخدام البيانات الحقيقية للطلب
        $orderNumber = (string)$userPlan->id;
        $orderAmount = number_format($userPlan->price, 2, '.', '');
        $orderCurrency = "USD";
        $orderDescription = "user_plan_id #" . $userPlan->id;

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
            'methods' => ['card', 'applepay', 'googlepay'],
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

            Log::info('MontyPay Response for Plan:', [
                'status' => $response->status(),
                'data' => $paymentData,
                'user_plan_id' => $userPlan->id
            ]);

            return [
                'success' => true,
                'data' => $paymentData
            ];
        } else {
            Log::error('MontyPay failed for plan: ' . $userPlan->id, [
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
     * إنشاء سجل الدفع للخطط في قاعدة البيانات
     */
    public static function createPaymentRecordForPlan($userPlan, $user, $paymentData)
    {
        return Payment_Plan::create([
            'user_plan_id' => $userPlan->id,
            'user_id' => $user->id,
            'payment_method' => 'montypay',
            'amount' => $userPlan->price,
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
     * التحقق من وجود عملية دفع معلقة للخطة
     */
    public static function hasPendingPaymentForPlan($userPlanId)
    {
        $payment = Payment_Plan::where('user_plan_id', $userPlanId)
            ->where('status', 'pending')
            ->first();

        if ($payment && isset($payment->payment_details['montypay_redirect_url'])) {
            return $payment->payment_details['montypay_redirect_url'];
        }

        return false;
    }

    /**
     * حذف المدفوعات المعلقة للخطة
     */
    public static function deletePendingPaymentsForPlan($userPlanId)
    {
        $deletedCount = Payment_Plan::where('user_plan_id', $userPlanId)
            ->where('status', 'pending')
            ->delete();

        return $deletedCount;
    }
}
