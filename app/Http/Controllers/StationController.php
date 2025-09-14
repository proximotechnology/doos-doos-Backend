<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = Station::query();

            if ($request->has('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            // استخدام Pagination بدلاً من get()
            $perPage = $request->get('per_page', 3); // افتراضي 15 عنصر في الصفحة
            $stations = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $stations
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching stations: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب المحطات',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'lat' => 'required|numeric',
            'lang' => 'required|numeric'
        ]);

        $station = Station::create($validated);

        return response()->json([
            'success' => true,
            'data' => $station
        ], 201);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function show($id)
    {
        $station = Station::find($id);

        if (!$station) {
            return response()->json([
                'success' => false,
                'message' => 'Station not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $station
        ]);
    }



    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Station $station)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $station = Station::find($id);

        if (!$station) {
            return response()->json([
                'success' => false,
                'message' => 'Station not found'
            ], 404);
        }

        // استخدام validate() المدمجة
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'lat' => 'sometimes|numeric',
            'lang' => 'sometimes|numeric'
        ]);

        $station->update($validatedData);

        return response()->json([
            'success' => true,
            'data' => $station
        ]);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $station = Station::find($id);

        if (!$station) {
            return response()->json([
                'success' => false,
                'message' => 'Station not found'
            ], 404);
        }

        $station->delete();

        return response()->json([
            'success' => true,
            'message' => 'Station deleted successfully'
        ]);
    }
}
