<?php

namespace App\Http\Controllers;

use App\Models\Driver_Price;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

class DriverPriceController extends Controller
{

    public function index()
    {
        $driver_price  = Driver_Price::find(1);
        return response()->json(['driver_price' => $driver_price]);
    }


    public function store(Request $request)
    {
        //
    }


    public function update(Request $request)
    {
        $id = 1;
        $validate = Validator::make($request->all(), [
            'price' => 'required',
        ]);

        if ($validate->fails()) {
            return response()->json(['errors' => $validate->errors()]);
        }

        $driver_price = Driver_Price::find($id);
        if (!$driver_price) {
            return response()->json(['errors' => 'Driver Price Not Found']);
        }
        $driver_price->update([
            'price' => $request->price
        ]);

        return response()->json(['success' => 'Driver Price Updated Successfully']);
    }


    public function destroy(Driver_Price $driver_Price)
    {
        //
    }
}
