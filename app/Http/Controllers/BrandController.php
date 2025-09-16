<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\ModelYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function getAllBrandsWithModels(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Read-BrandCars')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
        try {
            $query = Brand::query();

            // الفلترة حسب اسم البراند
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            $perPage = $request->get('per_page', 2);  // عدد العناصر في الصفحة (افتراضي 15)

            // جلب البراندات مع التصفية إذا وجدت
            $brands = $query->paginate($perPage);

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

    public function getAllBrands_user(Request $request)
    {
        try {
            $query = Brand::select('id', 'name');

            // الفلترة حسب اسم الماركة
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            // جلب النتائج
            $brands = $query->get();

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
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Show-BrandCar')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
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

        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Read-ModelCars')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }

        try {
            $query = CarModel::query()->with(['brand', 'years']);

            // الفلترة حسب brand_id
            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // الفلترة حسب اسم الموديل
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            // ترتيب النتائج
            $query->orderBy('name');

            // استخدام pagination مع إمكانية تحديد عدد العناصر من الـ request
            $perPage = $request->get('per_page', 2);  // افتراضي 15 عنصر في الصفحة
            $models = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $models,
                'message' => 'تم جلب الموديلات بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching car models: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الموديلات',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function index_model(Request $request)
    {
        try {
            $query = CarModel::query()->select('id', 'name');

            // الفلترة حسب brand_id
            if ($request->filled('brand_id')) {
                $query->where('brand_id', $request->brand_id);
            }

            // الفلترة حسب اسم الموديل
            if ($request->filled('name')) {
                $query->where('name', 'like', '%' . $request->name . '%');
            }

            // ترتيب النتائج
            $query->orderBy('name');

            // جلب جميع النتائج بدون pagination
            $models = $query->get();

            return response()->json([
                'success' => true,
                'data' => $models,
                'message' => 'تم جلب الموديلات بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching car models: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب الموديلات',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function getYearsByModel($model_id, Request $request)
    {
        try {
            // التحقق من وجود الموديل
            $model = CarModel::with('brand')->find($model_id);

            if (!$model) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على الموديل'
                ], 404);
            }

            // استخدام pagination مع إمكانية تحديد عدد العناصر من الـ request
            $perPage = $request->get('per_page', 2);  // افتراضي 15 عنصر في الصفحة

            // جلب السنوات الخاصة بالموديل مع pagination
            $years = ModelYear::where('car_model_id', $model_id)
                ->orderBy('year', 'asc')
                ->paginate($perPage);

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

    public function getModelYears_user(Request $request, $car_model_id)
    {
        try {
            // التحقق من وجود الموديل
            $carModel = CarModel::find($car_model_id);

            if (!$carModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'الموديل غير موجود'
                ], 404);
            }

            // جلب السنوات المرتبطة بالموديل مع تحديد الحقول المطلوبة فقط
            $years = ModelYear::where('car_model_id', $car_model_id)
                ->select('id', 'year')
                ->orderBy('year', 'desc')  // ترتيب السنوات تنازلياً
                ->get();

            return response()->json([
                'success' => true,
                'data' => $years,
                'message' => 'تم جلب سنوات الموديل بنجاح'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching model years: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب سنوات الموديل',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Create-BrandCar')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'make_id' => 'required|string|unique:brands,make_id',
                'name' => 'required|string|max:255',
                'country' => 'nullable|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',  // إضافة التحقق من الصورة
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'errors' => $validator->errors()
                ], 422);
            }

            // معالجة صورة البراند بنفس طريقة storeCar
            $data = $request->all();

            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = Str::random(32) . '.' . $image->getClientOriginalExtension();
                $imagePath = 'brand_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($image));
                $data['image'] = url('api/storage/' . $imagePath);  // استخدام url بدلاً من المسار فقط
            }

            // إنشاء البراند
            $brand = Brand::create($data);

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
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Update-BrandCar')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }
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
                'country' => 'nullable|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',  // إضافة التحقق من الصورة
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'errors' => $validator->errors()
                ], 422);
            }

            // معالجة صورة البراند بنفس طريقة store
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

            // معالجة صورة البراند
            if ($request->hasFile('image')) {
                // حذف الصورة القديمة إذا كانت موجودة
                if ($brand->image) {
                    $oldImagePath = str_replace(url('api/storage/'), '', $brand->image);
                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }

                $image = $request->file('image');
                $imageName = Str::random(32) . '.' . $image->getClientOriginalExtension();
                $imagePath = 'brand_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($image));
                $updateData['image'] = url('api/storage/' . $imagePath);
            } elseif ($request->has('image') && $request->image === null) {
                // إذا تم إرسال قيمة null لحذف الصورة
                if ($brand->image) {
                    $oldImagePath = str_replace(url('api/storage/'), '', $brand->image);
                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }
                $updateData['image'] = null;
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

    public function getModelsByBrand($brand_id, Request $request)
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

            // استخدام pagination مع إمكانية تحديد عدد العناصر من الـ request
            $perPage = $request->get('per_page', 2);  // افتراضي 15 عنصر في الصفحة

            // جلب الموديلات الخاصة بالبراند مع السنوات مع pagination
            $models = CarModel::where('brand_id', $brand_id)
                ->with(['brand', 'years'])
                ->orderBy('name', 'asc')
                ->paginate($perPage);

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
