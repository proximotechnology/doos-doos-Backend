<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Read-Plans')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
        try {
            $query = Plan::with('features');  // تحميل العلاقة features

            // Apply filters if provided
            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            if ($request->has('price')) {
                $query->where('price', $request->price);
            }

            if ($request->has('car_limite')) {
                $query->where('car_limite', $request->car_limite);
            }

            if ($request->has('count_day')) {
                $query->where('count_day', $request->count_day);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            // استخدام Pagination بنفس الطريقة
            $perPage = $request->get('per_page', 15);  // افتراضي 15 عنصر في الصفحة
            $plans = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $plans->items(),
                'meta' => [
                    'total' => $plans->total(),
                    'per_page' => $plans->perPage(),
                    'current_page' => $plans->currentPage(),
                    'last_page' => $plans->lastPage(),
                ],
                'pagination' => [
                    'current_page' => $plans->currentPage(),
                    'per_page' => $plans->perPage(),
                    'total' => $plans->total(),
                    'last_page' => $plans->lastPage(),
                    'from' => $plans->firstItem(),
                    'to' => $plans->lastItem(),
                    'first_page_url' => $plans->url(1),
                    'last_page_url' => $plans->url($plans->lastPage()),
                    'next_page_url' => $plans->nextPageUrl(),
                    'prev_page_url' => $plans->previousPageUrl(),
                    'path' => $plans->path(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching plans: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الخطط',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth('sanctum')->user()->can('Create-Plan')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric',
                'car_limite' => 'required|integer',
                'count_day' => 'required|integer',
            ]);

            // Set default is_active to 1 if not provided
            if (!isset($validated['is_active'])) {
                $validated['is_active'] = 1;
            }

            $plan = Plan::create($validated);

            return response()->json([
                'message' => 'Plan stored successfully',
                'data' => $plan
            ], 201);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Plan $plan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Plan $plan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (auth('sanctum')->user()->can('Update-Plan')) {
            $plan = Plan::findOrFail($id);

            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric',
                'car_limite' => 'sometimes|integer',
                'count_day' => 'sometimes|integer',
                'is_active' => 'sometimes|boolean',
            ]);

            $plan->update($validatedData);

            return response()->json([
                'message' => 'Plan updated successfully',
                'data' => $plan
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan)
    {
        //
    }
}
