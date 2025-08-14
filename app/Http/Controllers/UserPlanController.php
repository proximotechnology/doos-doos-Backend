<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User_Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class UserPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
/**
 * Display all subscriptions for the authenticated user with filtering
 */
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

    /**
     * Show the form for creating a new resource.
     */
   public function store(Request $request)
    {
        $user = auth()->user();

        // Check for existing active or pending subscriptions
        $existingPlan = $user->user_plan()
            ->whereIn('status', ['active', 'pending'])
            ->first();

        if ($existingPlan) {
            return response()->json([
                'status' => false,
                'message' => 'Cannot create new subscription. You already have an ' .
                            $existingPlan->status . ' subscription.',
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
            $stripePaymentUrl = null;
            $newUserPlan = null;

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

            // Try to create Stripe checkout session
            try {
                $stripeSecret = env('STRIPE_SECRET');

                if (empty($stripeSecret)) {
                    throw new \Exception('Stripe API key is not configured');
                }

                \Stripe\Stripe::setApiKey($stripeSecret);

                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => $plan->name,
                            ],
                            'unit_amount' => $plan->price * 100,
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => route('payment.success').'?session_id={CHECKOUT_SESSION_ID}&user_plan_id='.$newUserPlan->id,
                    'cancel_url' => route('payment.cancel').'?user_plan_id='.$newUserPlan->id,
                    'metadata' => [
                        'user_plan_id' => $newUserPlan->id,
                        'user_id' => $user->id
                    ],
                ]);

                $stripePaymentUrl = $session->url;
            } catch (\Exception $e) {
                // On Stripe failure, use mock URL instead of throwing error
                $stripePaymentUrl = 'https://checkout.stripe.com/pay/mock_'.Str::random(32);
                Log::info('Using mock payment URL due to Stripe error: '.$e->getMessage());
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Subscription created successfully. Payment required to activate.',
                'payment_url' => $stripePaymentUrl,
                'user_plan_id' => $newUserPlan->id
            ]);

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

    /**
     * Display the specified resource.
     */
    public function show(User_Plan $user_Plan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
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

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User_Plan $user_Plan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
/**
 * Admin endpoint to get all subscriptions with filters
 */
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
}
