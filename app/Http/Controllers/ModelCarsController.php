<?php

namespace App\Http\Controllers;

use App\Models\ModelCars;
use Illuminate\Http\Request;

class ModelCarsController extends Controller
{
     public function index()
    {
        $modelCars = ModelCars::all();
        return response()->json($modelCars);
    }

    // Store a new model car (admin only)
    public function store(Request $request)
    {

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $modelCar = ModelCars::create($request->all());
        return response()->json($modelCar, 201);
    }

    // Get a single model car (for both admin and users)
    public function show($modelCar)
    {
        $x=ModelCars::findorfail($modelCar);
        return response()->json($x);
    }

    // Update a model car (admin only)
    public function update(Request $request,  $modelCar)
    {
        $x=ModelCars::findorfail($modelCar);


        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $modelCar->update($request->all());
        return response()->json($modelCar);
    }

    // Delete a model car (admin only)
    public function destroy($modelCar)
    {
        $x=ModelCars::findorfail($modelCar);

        $modelCar->delete();
        return response()->json(null, 204);
    }
}
