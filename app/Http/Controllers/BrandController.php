<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\ModelYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BrandController extends Controller
{
    public function getAllBrandsWithModels()
    {
        try {
            // جلب جميع الماركات مع الموديلات وسنوات الإنتاج
            $brands = Brand::get();

            return response()->json([
                'success' => true,
                'data' => $brands,
                'message' => 'تم جلب البيانات بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }



    public function show($id)
    {
        try {
            // جلب جميع الماركات مع الموديلات وسنوات الإنتاج
            $brands = Brand::findorfail($id);

            return response()->json([
                'success' => true,
                'data' => $brands,
                'message' => 'تم جلب البيانات بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }


    public function index(Request $request)
    {
        try {
            // استخدام query() بدلاً من all() لبناء استعلام قابل للتعديل
            $query = CarModel::query();

            // الفلترة حسب brand_id إذا تم إرساله
            if ($request->has('brand_id') && $request->brand_id) {
                $query->where('brand_id', $request->brand_id);
            }

            // الفلترة حسب اسم الموديل إذا تم إرساله
            if ($request->has('name') && $request->name) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            // ترتيب النتائج
            $query->orderBy('name');

            $models = $query->with('brand');

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

    public function getYearsByModel($model_id)
    {
        try {
            // التحقق من وجود الموديل
            $model = CarModel::find($model_id);

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على الموديل'
                ], 404);
            }

            // جلب السنوات الخاصة بالموديل
            $years = ModelYear::where('car_model_id', $model_id)
                             ->orderBy('year', 'asc')
                             ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'model' => $model,
                    'years' => $years
                ],
                'message' => 'تم جلب السنوات بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب السنوات',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'make_id' => 'required|string|unique:brands,make_id',
                'name' => 'required|string|max:255',
                'country' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'errors' => $validator->errors()
                ], 422);
            }

            // إنشاء البراند
            $brand = Brand::create([
                'make_id' => $request->make_id,
                'name' => $request->name,
                'country' => $request->country
            ]);

            return response()->json([
                'success' => true,
                'data' => $brand,
                'message' => 'تم إنشاء البراند بنجاح'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء البراند',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }


    /**
 * تحديث براند معين
 */
    public function update(Request $request, $id)
    {
        try {
            // البحث عن البراند
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على البراند'
                ], 404);
            }

            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'make_id' => 'sometimes|required|string|unique:brands,make_id,' . $id,
                'name' => 'sometimes|required|string|max:255',
                'country' => 'nullable|string|max:255'
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
            if ($request->has('make_id')) {
                $updateData['make_id'] = $request->make_id;
            }
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('country')) {
                $updateData['country'] = $request->country;
            }

            // تحديث البراند
            $brand->update($updateData);

            return response()->json([
                'success' => true,
                'data' => $brand,
                'message' => 'تم تحديث البراند بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث البراند',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }



    public function destroy($id)
    {
        try {
            // البحث عن البراند
            $brand = Brand::find($id);

            if (!$brand) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على البراند'
                ], 404);
            }
            // حذف البراند
            $brand->delete();
            return response()->json([
                'success' => true,
                'message' => 'تم حذف البراند بنجاح'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف البراند',
            ], 500);
        }
    }



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

            // جلب الموديلات الخاصة بالبراند مع السنوات
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
