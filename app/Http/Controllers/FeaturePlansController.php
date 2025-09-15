<?php

namespace App\Http\Controllers;

use App\Models\FeaturePlans;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeaturePlansController extends Controller
{
    public function index(Request $request)
    {
        if (auth('sanctum')->user()->can('FeaturePlan-Read')) {
            try {
                $perPage = $request->get('per_page', 2);  // افتراضي 15 عنصر في الصفحة
                $features = FeaturePlans::with('plan')->paginate($perPage);

                return response()->json([
                    'status' => true,
                    'message' => 'Features retrieved successfully',
                    'data' => $features
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to retrieve features',
                    'error' => $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (auth('sanctum')->user()->can('FeaturePlan-Create')) {
            try {
                $validator = Validator::make($request->all(), [
                    'feature' => 'required|string|max:255',
                    'plan_id' => 'required|exists:plans,id'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Validation error',
                        'errors' => $validator->errors()
                    ], 422);
                }

                $featurePlan = FeaturePlans::create($request->only(['feature', 'plan_id']));

                return response()->json([
                    'status' => true,
                    'message' => 'Feature plan created successfully',
                    'data' => $featurePlan
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to create feature plan',
                    'error' => $e->getMessage()
                ], 500);
            }
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
    public function show($id)
    {
        if (auth('sanctum')->user()->can('FeaturePlan-Show')) {
            try {
                $featurePlan = FeaturePlans::with('cars')->find($id);
                if (!$featurePlan) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Feature plan not found'
                    ], 404);
                }
                return response()->json([
                    'status' => true,
                    'message' => 'Feature plan retrieved successfully',
                    'data' => $featurePlan
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to retrieve feature plan',
                    'error' => $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (auth('sanctum')->user()->can('FeaturePlan-Update')) {
            try {
                $featurePlan = FeaturePlans::find($id);

                if (!$featurePlan) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Feature plan not found'
                    ], 404);
                }

                $validator = Validator::make($request->all(), [
                    'feature' => 'sometimes|required|string|max:255',
                    'plan_id' => 'sometimes|required|exists:plans,id'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Validation error',
                        'errors' => $validator->errors()
                    ], 422);
                }

                $featurePlan->update($request->only(['feature', 'plan_id']));

                return response()->json([
                    'status' => true,
                    'message' => 'Feature plan updated successfully',
                    'data' => $featurePlan
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to update feature plan',
                    'error' => $e->getMessage()
                ], 500);
            }
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
    public function destroy($id)
    {
        if (auth('sanctum')->user()->can('FeaturePlan-Delete')) {
            try {
                $featurePlan = FeaturePlans::find($id);

                if (!$featurePlan) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Feature plan not found'
                    ], 404);
                }

                $featurePlan->delete();

                return response()->json([
                    'status' => true,
                    'message' => 'Feature plan deleted successfully'
                ], 200);
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to delete feature plan',
                    'error' => $e->getMessage()
                ], 500);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
    }
}
