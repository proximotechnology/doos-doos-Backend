<?php

namespace App\Http\Controllers;

use App\Models\ModelYear;
use App\Models\CarModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ModelYearController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $query = ModelYear::with('model.brand');

            // الفلترة حسب car_model_id إذا تم إرساله
            if ($request->has('car_model_id') && $request->car_model_id) {
                $query->where('car_model_id', $request->car_model_id);
            }

            // الفلترة حسب السنة إذا تم إرساله
            if ($request->has('year') && $request->year) {
                $query->where('year', $request->year);
            }

            // ترتيب النتائج
            $query->orderBy('year', 'desc');

            $years = $query->get();

            return response()->json([
                'success' => true,
                'data' => $years,
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'car_model_id' => 'required|exists:car_models,id',
                'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'errors' => $validator->errors()
                ], 422);
            }

            // التحقق من عدم تكرار السنة لنفس الموديل
            $existingYear = ModelYear::where('car_model_id', $request->car_model_id)
                                    ->where('year', $request->year)
                                    ->first();

            if ($existingYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'هذه السنة موجودة بالفعل لهذا الموديل'
                ], 422);
            }

            $imageUrl = null;
            // حفظ الصورة إذا تم رفعها
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = Str::random(32) . '.' . $image->getClientOriginalExtension();
                $imagePath = 'model_year_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($image));
                $imageUrl = url('api/storage/' . $imagePath);
            }

            // إنشاء السنة
            $modelYear = ModelYear::create([
                'car_model_id' => $request->car_model_id,
                'year' => $request->year,
                'image' => $imageUrl
            ]);

            return response()->json([
                'success' => true,
                'data' => $modelYear->load('model.brand'),
                'message' => 'تم إنشاء السنة بنجاح'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء إنشاء السنة',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $modelYear = ModelYear::with('model.brand')->find($id);

            if (!$modelYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على السنة'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $modelYear,
                'message' => 'تم جلب السنة بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب السنة',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ModelYear $modelYear)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $modelYear = ModelYear::find($id);

            if (!$modelYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على السنة'
                ], 404);
            }

            // التحقق من البيانات
            $validator = Validator::make($request->all(), [
                'car_model_id' => 'sometimes|required|exists:car_models,id',
                'year' => 'sometimes|required|integer|min:1900|max:' . (date('Y') + 1),
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطأ في التحقق من البيانات',
                    'errors' => $validator->errors()
                ], 422);
            }

            // التحقق من عدم تكرار السنة لنفس الموديل (إذا تم تغيير السنة أو الموديل)
            if ($request->has('year') || $request->has('car_model_id')) {
                $carModelId = $request->car_model_id ?? $modelYear->car_model_id;
                $year = $request->year ?? $modelYear->year;

                $existingYear = ModelYear::where('car_model_id', $carModelId)
                                        ->where('year', $year)
                                        ->where('id', '!=', $id)
                                        ->first();

                if ($existingYear) {
                    return response()->json([
                        'success' => false,
                        'message' => 'هذه السنة موجودة بالفعل لهذا الموديل'
                    ], 422);
                }
            }

            $updateData = [];
            if ($request->has('car_model_id')) {
                $updateData['car_model_id'] = $request->car_model_id;
            }
            if ($request->has('year')) {
                $updateData['year'] = $request->year;
            }

            // حفظ الصورة إذا تم رفعها
            if ($request->hasFile('image')) {
                // حذف الصورة القديمة إذا كانت موجودة
                if ($modelYear->image) {
                    $oldImagePath = str_replace(url('api/storage/'), '', $modelYear->image);
                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                }

                $image = $request->file('image');
                $imageName = Str::random(32) . '.' . $image->getClientOriginalExtension();
                $imagePath = 'model_year_images/' . $imageName;
                Storage::disk('public')->put($imagePath, file_get_contents($image));
                $updateData['image'] = url('api/storage/' . $imagePath);
            }

            $modelYear->update($updateData);

            return response()->json([
                'success' => true,
                'data' => $modelYear->load('model.brand'),
                'message' => 'تم تحديث السنة بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء تحديث السنة',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $modelYear = ModelYear::find($id);

            if (!$modelYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على السنة'
                ], 404);
            }

            // حذف الصورة إذا كانت موجودة
            if ($modelYear->image) {
                $imagePath = str_replace(url('api/storage/'), '', $modelYear->image);
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }

            $modelYear->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف السنة بنجاح'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء حذف السنة',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    /**
     * جلب سنوات موديل معين
     */
    public function getYearsByModel($car_model_id)
    {
        try {
            // التحقق من وجود الموديل
            $carModel = CarModel::with('brand')->find($car_model_id);

            if (!$carModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على الموديل'
                ], 404);
            }

            // جلب السنوات الخاصة بالموديل
            $years = ModelYear::where('car_model_id', $car_model_id)
                             ->orderBy('year', 'desc')
                             ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'model' => $carModel,
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
}
