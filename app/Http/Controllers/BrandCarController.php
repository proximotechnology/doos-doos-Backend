<?php

namespace App\Http\Controllers;

use App\Models\BrandCar;
use Illuminate\Http\Request;

class BrandCarController extends Controller
{
    public function index()
    {
        $modelCars = BrandCar::all();
        return response()->json($modelCars);
    }

    // Store a new model car (admin only)
    public function store(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $modelCar = BrandCar::create($request->all());
        return response()->json($modelCar, 201);
    }

    // Get a single model car (for both admin and users)
    public function show($modelCar)
    {
        $x=BrandCar::findorfail($modelCar);
        return response()->json($x);
    }

    // Update a model car (admin only)
    public function update(Request $request,  $modelCar)
    {
        $x=BrandCar::findorfail($modelCar);


        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $x->update($request->all());
        return response()->json($x);
    }

    // Delete a model car (admin only)
    public function destroy($modelCar)
    {
        $x=BrandCar::findorfail($modelCar);

        $x->delete();
        return response()->json(null, 204);
    }
}
