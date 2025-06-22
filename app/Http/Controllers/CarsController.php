<?php

namespace App\Http\Controllers;

use App\Models\Cars;
use Illuminate\Http\Request;

use App\Models\Cars_Features;
use App\Models\User;
use App\Models\Company;
use App\Models\Cars_Image;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

use function PHPUnit\Framework\isEmpty;

class CarsController extends Controller
{


    public function filterCars(Request $request)
    {
        $query = Cars::query();

        // فلترة بناءً على make و model و status و address
        if ($request->filled('make')) {
            $query->where('make', $request->make);
        }

        if ($request->filled('model')) {
            $query->where('model', $request->model);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        // فلترة السنة بين year_from و year_to
        if ($request->filled('year_from')) {
            $query->where('year', '>=', $request->year_from);
        }

        if ($request->filled('year_to')) {
            $query->where('year', '<=', $request->year_to);
        }

        // فلترة السعر
        if ($request->filled('price_min')) {
            $query->where('price', '>=', $request->price_min);
        }

        if ($request->filled('price_max')) {
            $query->where('price', '<=', $request->price_max);
        }

        // فلترة حسب الموقع الجغرافي (اختياري - حسب مدى القرب، لو عندك logic للـ distance مثلاً)
        if ($request->filled('lat') && $request->filled('lang')) {
            $lat = $request->lat;
            $lang = $request->lang;

            // هذا مثال بسيط إذا كنت فقط تريد سيارات في نفس الإحداثيات
            $query->where('lat', $lat)->where('lang', $lang);

            // إذا كنت تريد البحث في نطاق معين، يمكن حساب المسافة باستخدام Haversine formula مثلاً
            // أخبرني إذا أردت تفعيلها
        }

        $cars = $query->get();

        return response()->json([
            'status' => true,
            'data' => $cars
        ]);
    }

    public function index()
    {
        $cars = Cars::with('cars_features', 'car_image')->where('status', 'active')->where('is_rented', 0)->get();

        return response()->json([
            'status' => true,
            'data' => $cars
        ]);
    }

    public function get_all_mycars()
    {
        $user = auth()->user();

        $cars = Cars::with('cars_features', 'car_image')->where('owner_id', $user->id)->get();

        return response()->json([
            'status' => true,
            'data' => $cars
        ]);
    }



    public function storeCar(Request $request)
    {

        $userlogged = auth()->user();

        $user = User::find($userlogged->id);



        $validator = Validator::make($request->all(), [
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . date('Y'),
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'vin' => 'required|string|size:17',
            'number' => 'required|string|max:50',
            'price' => 'required|numeric',
            'lat' => 'required',
            'lang' => 'required',

            'image_license' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'number_license' => 'nullable|string|size:17',
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

            'company.legal_name' => 'nullable|string|max:255',
            'company.num_of_employees' => 'nullable|numeric|max:255',

            'company.is_under_vat' => 'nullable|numeric|max:255',
            'company.vat_num' => 'nullable|string|max:255',
            'company.zip_code' => 'nullable|string|max:255',
            'company.country' => 'nullable|string|max:255',
            'company.address_1' => 'nullable|string|max:255',
            'company.address_2' => 'nullable|string|max:255',
            'company.city' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {

            // التحقق من وجود صورة الرخصة ثم حفظها
            if ($request->hasFile('image_license')) {
                $image = $request->file('image_license');
                $path = $image->store('car_images', 'public');
            }






            // Step 1: حفظ السيارة
            $car = Cars::create([
                'owner_id' => auth()->id(),
                'make' => $request->make,
                'model' => $request->model,
                'year' => $request->year,
                'price' => $request->price,
                // 'day' => now()->day,
                'lang' => $request->lang,
                'lat' => $request->lat,
                'address' => $request->address,
                'description' => $request->description,
                'number' => $request->number,
                'vin' => $request->vin,
                'image_license' => $path ?? null,
                'number_license' => $request->number_license,
                'state' => $request->state,
                'description_condition' => $request->description_condition,
                'advanced_notice' => $request->advanced_notice,
                'min_day_trip' => $request->min_day_trip,
                'max_day_trip' => $request->max_day_trip
            ]);


            $lastcar = Cars::where('owner_id', $user->id)->get();



            $company_info = Company::where('user_id', $user->id)->first();


            if ($lastcar->count() > 1 && $company_info == null) {

                if (!$request->has('company')) {
                    return response()->json([
                        'status' => false,
                        'message' => 'يجب ادخال معلومات الشركة',
                    ]);
                }

                $company_info = Company::create([
                    'user_id' => $user->id,
                    'legal_name' => $request->company['legal_name'],
                    'num_of_employees' => $request->company['num_of_employees'],
                    'is_under_vat' => $request->company['is_under_vat'],
                    'vat_num' => $request->company['vat_num'],
                    'zip_code' => $request->company['zip_code'],
                    'country' => $request->company['country'],
                    'address_1' => $request->company['address_1'],
                    'address_2' => $request->company['address_2'],
                    'city' => $request->company['city'],
                ]);

                $user->is_company = 1;
                $user->save();
            }



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
            'description' => 'sometimes|string|nullable',
            'address' => 'sometimes|string|nullable',
            'vin' => 'sometimes|string|size:17',
            'number' => 'sometimes|string|max:50',
            'price' => 'sometimes|numeric',
            'lat' => 'sometimes',
            'lang' => 'sometimes',
            'status' => 'sometimes|in:pending,active,inactive',
            'image_license' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            'number_license' => 'sometimes|string|size:17',
            'state' => 'sometimes|string|max:100',
            'description_condition' => 'sometimes|string|nullable',
            'advanced_notice' => 'sometimes|string|max:10|nullable',
            'min_day_trip' => 'sometimes|integer|nullable',
            'max_day_trip' => 'sometimes|integer|nullable',

            'features.mileage_range' => 'sometimes|string|nullable',
            'features.transmission' => 'sometimes|in:automatic,manual|nullable',
            'features.mechanical_condition' => 'sometimes|in:good,not_working,excellent|nullable',
            'features.all_have_seatbelts' => 'sometimes|boolean|nullable',
            'features.num_of_door' => 'sometimes|integer|nullable',
            'features.num_of_seat' => 'sometimes|integer|nullable',
            'features.additional_features' => 'sometimes|array|nullable',
            'images.*' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
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
            $car = Cars::where('id', $id)->where('owner_id', auth()->id())->first();

            if (!$car) {
                return response()->json([
                    'status' => false,
                    'message' => 'السيارة غير موجودة.',
                ], 404);
            }

            if ($user->id != $car->owner_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'this car not yours',
                ]);
            }





            $data = $request->all();

            // التحقق من وجود صورة الرخصة ثم حفظها
            if ($request->hasFile('image_license')) {
                $image = $request->file('image_license');
                $path = $image->store('car_images', 'public');
                $data['image_license'] = $path;
            }

            // تحديث بيانات السيارة
            $car->update(Arr::only($data, [
                'make',
                'owner_id',
                'model',
                'year',
                'status',
                'price',
                'day',
                'lang',
                'lat',
                'address',
                'description',
                'number',
                'vin',
                'image_license',
                'number_license',
                'state',
                'description_condition',
                'advanced_notice',
                'min_day_trip',
                'max_day_trip',
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

        if (!$car->is_rented == 0) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف سيارة مستأجرة',
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
