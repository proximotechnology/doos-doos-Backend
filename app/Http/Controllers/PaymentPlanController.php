<?php

namespace App\Http\Controllers;

use App\Models\Payment_Plan;
use App\Models\User_Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Illuminate\Support\Facades\Validator;

class PaymentPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
   public function success(Request $request)
    {
            $stripeSecret = env('STRIPE_SECRET');

            \Stripe\Stripe::setApiKey($stripeSecret);


        try {
            $sessionId = $request->session_id;
            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            // Find the payment plan
            $paymentPlan = Payment_Plan::where('transaction_id', $sessionId)->firstOrFail();

            if ($session->payment_status === 'paid') {
                // Update payment plan
                $paymentPlan->update([
                    'status' => 'completed',
                    'paid_at' => now(),
                    'payment_details' => array_merge($paymentPlan->payment_details, [
                        'payment_intent' => $session->payment_intent,
                        'payment_status' => $session->payment_status,
                        'receipt_url' => $session->payment_intent ?
                            \Stripe\PaymentIntent::retrieve($session->payment_intent)->charges->data[0]->receipt_url : null
                    ])
                ]);

                // Update user plan
                $userPlan = User_Plan::find($paymentPlan->user_plan_id);
                $userPlan->update([
                    'status' => 'active',
                    'is_paid' => 1,
                    'date_from' => now(),
                    'date_end' => now()->addDays($userPlan->plan->count_day)
                ]);

                // Get the user ID from the user plan
                $userId = $userPlan->user_id;

                // Update all pending cars for this user
                \App\Models\Cars::where('owner_id', $userId)
                    ->where('is_paid', 0) // Assuming 0 means pending
                    ->where('status', 'pending')
                    ->update(['is_paid' => 1]);

                return response()->json([
                    'status' => true,
                    'message' => 'Payment completed successfully. All pending cars have been activated.',
                    'data' => [
                        'payment_plan' => $paymentPlan,
                        'receipt_url' => $paymentPlan->payment_details['receipt_url'] ?? null,
                        'updated_cars_count' => \App\Models\Cars::where('owner_id', $userId)
                            ->where('is_paid', 0)
                            ->count()
                    ]
                ]);
            }

            return $this->paymentFailed($paymentPlan, 'Payment not completed');

        } catch (\Exception $e) {
            Log::error('Payment success error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error processing payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request)
    {
        try {
            $sessionId = $request->session_id;
            $paymentPlan = Payment_Plan::where('transaction_id', $sessionId)->firstOrFail();

            return $this->paymentFailed($paymentPlan, 'Payment was cancelled');

        } catch (\Exception $e) {
            Log::error('Payment cancel error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error processing cancellation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function paymentFailed(Payment_Plan $paymentPlan, $message)
    {
        $paymentPlan->update([
            'status' => 'failed',
            'payment_details' => array_merge($paymentPlan->payment_details, [
                'failed_at' => now(),
                'failure_reason' => $message
            ])
        ]);

        // Optionally delete the user plan if payment failed
        User_Plan::where('id', $paymentPlan->user_plan_id)->delete();

        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => [
                'payment_plan_id' => $paymentPlan->id,
                'status' => 'failed'
            ]
        ], 400);
    }





    /**
 * Admin endpoint to manually activate a subscription
 */

}
