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
    public function handleSuccess(Request $request)
    {
        $validated = $request->validate([
            'user_plan_id' => 'required|exists:user_plans,id',
            'transaction_id' => 'required',
            'recurring_token' => 'required'
        ]);

        DB::beginTransaction();

        try {
            $userPlan = User_Plan::findOrFail($validated['user_plan_id']);

            $userPlan->update([
                'status' => User_Plan::STATUS_ACTIVE,
                'is_paid' => true,
                'date_from' => now(),
                'date_end' => now()->addMonth()
            ]);

            $userPlan->paymentPlan()->create([
                'gateway' => 'montypay',
                'transaction_id' => $validated['transaction_id'],
                'recurring_token' => $validated['recurring_token'],
                'status' => 'paid'
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم تفعيل الاشتراك بنجاح'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'فشل تفعيل الاشتراك'
            ], 500);
        }
    }

    public function handleCancel(Request $request)
    {
        $validated = $request->validate([
            'user_plan_id' => 'required|exists:user_plans,id'
        ]);

        $userPlan = User_Plan::findOrFail($validated['user_plan_id']);
        $userPlan->update(['status' => User_Plan::STATUS_CANCELED]);

        return response()->json([
            'status' => true,
            'message' => 'تم إلغاء عملية الدفع'
        ]);
    }
}
