<?php

namespace App\Http\Controllers;

use App\Models\Cars;
use Illuminate\Http\Request;

use App\Models\Cars_Features;
use App\Models\Cars_Image;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;



class CarsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function get_all_mycars(){
        $user = auth()->user();

        $cars = Cars::with('cars_features' , 'car_image')->where('owner_id', $user->id)->get();

        return response()->json([
            'status' => true,
            'data' => $cars
        ]);
    }



    public function storeCar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . date('Y'),
            'price' => 'required|numeric',
            'lang' => 'required',
            'lat' => 'required',
            'description' => 'nullable|string',
            'number' => 'required|string|max:50',
            'vin' => 'required|string|size:17',
            'number_license' => 'required|string|size:17',
            'state' => 'required|string|max:100',
            'description_condition' => 'nullable|string',
            'advanced_notice' => 'nullable|string|max:10',
            'min_day_trip' => 'nullable|integer',
            'max_day_trip' => 'nullable|integer',
            'features.mileage_range' => 'nullable|string',
            'features.transmission' => 'nullable|in:automatic,manual',
            'features.mechanical_condition' => 'nullable|in:good,not_working,excellent',
            'features.all_have_seatbelts' => 'nullable|boolean',
            'features.num_of_door' => 'nullable|integer',
            'features.num_of_seat' => 'nullable|integer',
            'features.additional_features' => 'array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Step 1: حفظ السيارة
            $car = Cars::create([
                'owner_id' => auth()->id(),
                'make' => $request->make,
                'model' => $request->model,
                'year' => $request->year,
                'status' => 'pending',
                'price' => $request->price,
                'day' => now()->day,
                'lang' => $request->lang,
                'lat' => $request->lat,
                'address' => $request->address,
                'description' => $request->description,
                'number' => $request->number,
                'vin' => $request->vin,
            ]);




            // Step 2: حفظ المزايا
            if ($request->has('features')) {
                $car->cars_features()->create([
                    'mileage_range' => $request->features['mileage_range'],
                    'transmission' => $request->features['transmission'],
                    'mechanical_condition' => $request->features['mechanical_condition'],
                    'all_have_seatbelts' => $request->features['all_have_seatbelts'],
                    'num_of_door' => $request->features['num_of_door'],
                    'num_of_seat' => $request->features['num_of_seat'],
                    'additional_features' => json_encode($request->features['additional_features']),
                ]);
            }


            // Step 3: حفظ الصور
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('car_images', 'public');
                    Cars_Image::create([
                        'cars_id' => $car->id,
                        'image' => $path,
                    ]);
                }
            }

            DB::commit(); // تم كل شيء بنجاح

            return response()->json([
                'status' => true,
                'message' => 'Car created successfully.',
                'data' => $car->load(['cars_features', 'car_image']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack(); // تراجع عن كل العمليات

            Log::error('StoreCar failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء حفظ السيارة. تم التراجع عن كل العمليات.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function updateCar(Request $request, $id)
    {

        $validator = Validator::make($request->all(), [
            'make' => 'sometimes|string|max:255',
            'model' => 'sometimes|string|max:255',
            'year' => 'sometimes|integer|min:1900|max:' . date('Y'),
            'price' => 'sometimes|numeric',
            'lang' => 'sometimes',
            'lat' => 'sometimes',
            'description' => 'nullable|string',
            'number' => 'sometimes|string|max:50',
            'vin' => 'sometimes|string|size:17',
            'number_license' => 'sometimes|string|size:17',
            'state' => 'sometimes|string|max:100',
            'description_condition' => 'nullable|string',
            'advanced_notice' => 'nullable|string|max:10',
            'min_day_trip' => 'nullable|integer',
            'max_day_trip' => 'nullable|integer',
            'features.mileage_range' => 'nullable|string',
            'features.transmission' => 'nullable|in:automatic,manual',
            'features.mechanical_condition' => 'nullable|in:good,not_working,excellent',
            'features.all_have_seatbelts' => 'nullable|boolean',
            'features.num_of_door' => 'nullable|integer',
            'features.num_of_seat' => 'nullable|integer',
            'features.additional_features' => 'array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            $user = auth()->user();
            $car = Cars::where('id', $id)->where('owner_id', auth()->id())->firstOrFail();

            if ($user->id != $car->owner_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'this car not yours',
                ]);
            }

            // تحديث بيانات السيارة
            $car->update($request->only([
                'make',
                'model',
                'year',
                'price',
                'lang',
                'lat',
                'address',
                'description',
                'number',
                'vin'
            ]));

            // تحديث بيانات المزايا إن وُجدت
            if ($request->has('features')) {
                $featuresData = [
                    'mileage_range' => $request->features['mileage_range'] ?? null,
                    'transmission' => $request->features['transmission'] ?? null,
                    'mechanical_condition' => $request->features['mechanical_condition'] ?? null,
                    'all_have_seatbelts' => $request->features['all_have_seatbelts'] ?? null,
                    'num_of_door' => $request->features['num_of_door'] ?? null,
                    'num_of_seat' => $request->features['num_of_seat'] ?? null,
                    'additional_features' => isset($request->features['additional_features'])
                        ? json_encode($request->features['additional_features']) : null,
                ];

                if ($car->cars_features) {
                    $car->cars_features()->update($featuresData);
                } else {
                    $car->cars_features()->create($featuresData);
                }
            }

            // إضافة صور جديدة إن وُجدت
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('car_images', 'public');
                    Cars_Image::create([
                        'cars_id' => $car->id,
                        'image' => $path,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Car updated successfully.',
                'data' => $car->load(['cars_features', 'car_image']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UpdateCar failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تعديل السيارة.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function updateCarFeatures(Request $request, $car_id)
    {
        $validator = Validator::make($request->all(), [
            'features.mileage_range' => 'nullable|string|max:255',
            'features.transmission' => 'nullable|in:automatic,manual',
            'features.mechanical_condition' => 'nullable|in:good,not_working,excellent',
            'features.all_have_seatbelts' => 'nullable|boolean',
            'features.num_of_door' => 'nullable|integer',
            'features.num_of_seat' => 'nullable|integer',
            'features.additional_features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // تحقق أن السيارة موجودة وتخص المستخدم
            $car = Cars::where('id', $car_id)->where('owner_id', auth()->id())->first();



            $user = auth()->user();
            $car = Cars::where('id', $car_id)->where('owner_id', auth()->id())->firstOrFail();

            if ($user->id != $car->owner_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'this car not yours',
                ]);
            }



            if (!$car) {
                return response()->json([
                    'status' => false,
                    'message' => 'السيارة غير موجودة أو لا تملك صلاحية التعديل.',
                ], 404);
            }

            $features = $car->cars_features;

            if (!$features) {
                return response()->json([
                    'status' => false,
                    'message' => 'لم يتم العثور على مزايا لهذه السيارة.',
                ], 404);
            }

            $featuresData = $request->features;

            $features->update([
                'mileage_range' => $featuresData['mileage_range'] ?? $features->mileage_range,
                'transmission' => $featuresData['transmission'] ?? $features->transmission,
                'mechanical_condition' => $featuresData['mechanical_condition'] ?? $features->mechanical_condition,
                'all_have_seatbelts' => array_key_exists('all_have_seatbelts', $featuresData) ? $featuresData['all_have_seatbelts'] : $features->all_have_seatbelts,
                'num_of_door' => $featuresData['num_of_door'] ?? $features->num_of_door,
                'num_of_seat' => $featuresData['num_of_seat'] ?? $features->num_of_seat,
                'additional_features' => array_key_exists('additional_features', $featuresData) ? json_encode($featuresData['additional_features']) : $features->additional_features,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم تحديث المزايا بنجاح.',
                'data' => $features,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UpdateCarFeatures failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء التحديث.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }





    public function destroy($id)
    {
        $car = Cars::find($id);
        if (!$car) {
            return response()->json([
                'status' => false,
                'message' => 'السيارة غير موجودة.',
            ], 404);
        }

        $user = auth()->user();

        if ($user->id != $car->owner_id) {
            return response()->json([
                'status' => false,
                'message' => 'this car not yours',
            ]);
        }

        $car->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف السيارة بنجاح.',
        ]);
    }
}
