<?php

namespace App\Http\Controllers;

use App\Models\Cars_Features;
use Illuminate\Http\Request;
use App\Models\Cars;


class CarsFeaturesController extends Controller
{


    public function index()
    {
        //
    }


    public function show_features($id)
    {
        $car = Cars::find($id);

        if (!$car || $car->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'السيارة غير موجودة أو غير مفعّلة.',
            ], 404);
        }

        $features = Cars_Features::where('cars_id', $id)->get();

        return response()->json([
            'status' => true,
            'data' => $features,
        ]);
    }



    public function store(Request $request)
    {
        //
    }

    public function update(Request $request, Cars_Features $cars_Features)
    {
        //
    }


    public function destroy(Cars_Features $cars_Features)
    {
        //
    }
}
