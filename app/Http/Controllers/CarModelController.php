<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CarModelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $models = CarModel::with(['brand', 'years'])->get();

            return response()->json([
                'success' => true,
                'data' => $models,
                'message' => 'تم جلب الموديلات بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الموديلات',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        if (auth('sanctum')->user()->can('Create-ModelCar')) {
            try {
                // التحقق من البيانات
                $validator = Validator::make($request->all(), [
                    'brand_id' => 'required|exists:brands,id',
                    'name' => 'required|string|max:255|unique:car_models,name,NULL,id,brand_id,' . $request->brand_id
                ], [
                    'name.unique' => 'هذا الموديل موجود already لهذه الماركة'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'خطأ في التحقق من البيانات',
                        'errors' => $validator->errors()
                    ], 422);
                }

                // التحقق من وجود البراند
                $brand = Brand::find($request->brand_id);
                if (!$brand) {
                    return response()->json([
                        'success' => false,
                        'message' => 'البراند المحدد غير موجود'
                    ], 404);
                }

                // إنشاء الموديل
                $model = CarModel::create([
                    'brand_id' => $request->brand_id,
                    'name' => $request->name
                ]);

                return response()->json([
                    'success' => true,
                    'data' => $model->load('brand'),
                    'message' => 'تم إنشاء الموديل بنجاح'
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء إنشاء الموديل',
                    'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
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
        if (auth('sanctum')->user()->can('Show-ModelCar')) {
            try {
                $model = CarModel::with(['brand', 'years'])->find($id);

                if (!$model) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لم يتم العثور على الموديل'
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $model,
                    'message' => 'تم جلب الموديل بنجاح'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء جلب الموديل',
                    'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
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
     * Show the form for editing the specified resource.
     */
    public function edit(CarModel $carModel)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (auth('sanctum')->user()->can('Update-ModelCar')) {
            try {
                $model = CarModel::find($id);

                if (!$model) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لم يتم العثور على الموديل'
                    ], 404);
                }

                // التحقق من البيانات
                $validator = Validator::make($request->all(), [
                    'brand_id' => 'sometimes|required|exists:brands,id',
                    'name' => 'sometimes|required|string|max:255|unique:car_models,name,' . $id . ',id,brand_id,' . ($request->brand_id ?? $model->brand_id)
                ], [
                    'name.unique' => 'هذا الموديل موجود already لهذه الماركة'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'خطأ في التحقق من البيانات',
                        'errors' => $validator->errors()
                    ], 422);
                }

                // تحديث البيانات
                $updateData = [];
                if ($request->has('brand_id')) {
                    $updateData['brand_id'] = $request->brand_id;
                }
                if ($request->has('name')) {
                    $updateData['name'] = $request->name;
                }

                $model->update($updateData);

                return response()->json([
                    'success' => true,
                    'data' => $model->load('brand'),
                    'message' => 'تم تحديث الموديل بنجاح'
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'حدث خطأ أثناء تحديث الموديل',
                    'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
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
        try {
            $model = CarModel::find($id);

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على الموديل'
                ], 404);
            }

            // التحقق إذا كان هناك سنوات مرتبطة بالموديل
            if ($model->years()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن حذف الموديل لأنه يحتوي على سنوات مرتبطة'
                ], 422);
            }

            $model->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف الموديل بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف الموديل',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * جلب موديلات براند معين
     */
    public function getModelsByBrand($brand_id)
    {
        try {
            // التحقق من وجود البراند
            $brand = Brand::find($brand_id);

            if (!$brand) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على البراند'
                ], 404);
            }

            // جلب الموديلات الخاصة بالبراند
            $models = CarModel::where('brand_id', $brand_id)
                ->with(['brand', 'years'])
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'brand' => $brand,
                    'models' => $models
                ],
                'message' => 'تم جلب الموديلات بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الموديلات',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }
}
