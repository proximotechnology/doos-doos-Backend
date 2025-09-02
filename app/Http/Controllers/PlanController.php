<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Plan::query();

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

        // Paginate results
        $perPage = $request->per_page ?? 15;
        $plans = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $plans->items(),
            'meta' => [
                'total' => $plans->total(),
                'per_page' => $plans->perPage(),
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
            ]
        ]);
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



    $validate = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'car_limite' => 'required|integer',
            'count_day' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['errors' => $validate->errors()]);
        }



        // Set default is_active to 1 if not provided
        if (!isset($validated['is_active'])) {
            $validated['is_active'] = 1;
        }

        $plan = Plan::create($validated);

        return response()->json([
            'message' => 'Plan stored successfully',
            'data' => $plan
        ], 201);
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
    public function update(Request $request,  $id)
    {
        $plan=Plan::findorfail($id);

        $validate = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric',
                'car_limite' => 'sometimes|integer',
                'count_day' => 'sometimes|integer',

                'is_active' => 'sometimes|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json(['errors' => $validate->errors()]);
        }

            $plan->update($validate);

            return response()->json([
                'message' => 'Plan updated successfully',
                'data' => $plan
            ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Plan $plan)
    {
        //
    }
}
