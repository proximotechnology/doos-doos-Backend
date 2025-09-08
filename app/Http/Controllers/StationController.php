<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Station::query();

        if ($request->has('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        $stations = $query->get();

        return response()->json([
            'success' => true,
            'data' => $stations
        ]);
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


        $validated = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'lat' => 'sometimes|numeric',
            'lang' => 'sometimes|numeric'
        ]);

        if ($validated->fails()) {
            return response()->json(['errors' => $validated->errors()]);
        }


        $station->update($validated);

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
