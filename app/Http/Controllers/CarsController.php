<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Cars;
use Illuminate\Http\Request;

use App\Models\Cars_Features;
use App\Models\Cars_Image;
use App\Models\ModelYear;
use App\Models\Order_Booking;
use App\Models\Plan;
use App\Models\RejectionReason;
use App\Models\User;
use App\Models\User_Plan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;

class CarsController extends Controller
{


 /*   public function filterCars(Request $request)
    {
        $query = Cars::query()->with(['cars_features', 'car_image', 'model', 'brand', 'years'])
                        ->where('status', 'active'); // إضافة هذا الشرط


        // فلترة بناءً على make و model و status و address
        if ($request->filled('make')) {
            $query->where('make', $request->make);
        }

        if ($request->filled('model_id')) {
            $query->where('car_model_id', $request->model_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        // فلترة السنة بين year_from و year_to
        if ($request->filled('model_year_id')) {
            $query->where('model_year_id', '>=', $request->model_year_id);
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
            $query->where('lat', $lat)->where('lang', $lang->where('status', 'active'));
        }

        // الحصول على عدد العناصر في الصفحة (اختياري)
        $perPage = $request->input('per_page', 15); // القيمة الافتراضية 15 عنصر لكل صفحة

        // تطبيق pagination
        $cars = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $cars
        ]);
    }*/
    public function filterCars(Request $request)
    {
        $query = Cars::query()->with(['cars_features', 'car_image', 'model', 'brand', 'years'])
                        ->where('status', 'active'); // إضافة هذا الشرط

        // فلترة بناءً على make و model و status و address
        if ($request->filled('make')) {
            $query->where('make', $request->make);
        }

        if ($request->filled('model_id')) {
            $query->where('car_model_id', $request->model_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }



        if ($request->filled('driver_available')) {
            $query->where('driver_available', $request->driver_available);
        }


        if ($request->filled('address')) {
            $query->where('address', 'like', '%' . $request->address . '%');
        }

        // فلترة السنة بين year_from و year_to
        if ($request->filled('year_from') && $request->filled('year_to')) {
            // الحصول على معرفات السنوات التي تقع ضمن النطاق المطلوب
            $yearIds = ModelYear::whereBetween('year', [$request->year_from, $request->year_to])
                                ->pluck('id');

            // فلترة السيارات بناءً على معرفات السنوات
            $query->whereIn('model_year_id', $yearIds);
        }
        // إذا تم إرسال سنة واحدة فقط (year_from)
        elseif ($request->filled('year_from')) {
            $yearIds = ModelYear::where('year', '>=', $request->year_from)
                                ->pluck('id');
            $query->whereIn('model_year_id', $yearIds);
        }
        // إذا تم إرسال سنة واحدة فقط (year_to)
        elseif ($request->filled('year_to')) {
            $yearIds = ModelYear::where('year', '<=', $request->year_to)
                                ->pluck('id');
            $query->whereIn('model_year_id', $yearIds);
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
        }

        // الحصول على عدد العناصر في الصفحة (اختياري)
        $perPage = $request->input('per_page', 2); // القيمة الافتراضية 15 عنصر لكل صفحة

        // تطبيق pagination
        $cars = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $cars
        ]);
    }

    public function index()
    {
        $perPage = request()->get('per_page', 2); // عدد العناصر في الصفحة (افتراضي 15)

        $cars = Cars::with('cars_features', 'car_image', 'model', 'brand', 'years')
                    ->where('status', 'active')
                    ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $cars
        ]);
    }


    public function show($id)
    {
        $car = Cars::with('cars_features', 'car_image','model','brand','years')
                    ->find($id);

        if (!$car) {
            return response()->json([
                'status' => false,
                'message' => 'Car not found or not available'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $car
        ]);
    }


    public function get_all_mycars(Request $request)
    {
        $user = auth()->user();
        $perPage = $request->input('per_page', 2);

        $query = Cars::with(['cars_features','years', 'car_image', 'model', 'owner','brand','rejectionReasons'])
                    ->when($user->type != 1, function ($q) use ($user) {
                        return $q->where('owner_id', $user->id);
                    });

        // فلترة حسب model_car_id (موديل السيارة)
        if ($request->has('model_car_id')) {
            $query->where('car_model_id', $request->model_car_id);
        }

        if ($request->has('brand_car_id')) {
            $query->where('brand_id', $request->brand_car_id);
        }
        // فلترة حسب status (حالة السيارة)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // فلترة حسب price (السعر)
        if ($request->has('price')) {
            $query->where('price', $request->price);
        }

        // فلترة متقدمة للسعر (نطاق أسعار)
        if ($request->has(['min_price', 'max_price'])) {
            $query->whereBetween('price', [$request->min_price, $request->max_price]);
        }

        // فلترة حسب min_day_trip (الحد الأدنى لأيام التأجير)
        if ($request->has('min_day_trip')) {
            $query->where('min_day_trip', '>=', $request->min_day_trip);
        }

        // فلترة حسب max_day_trip (الحد الأقصى لأيام التأجير)
        if ($request->has('max_day_trip')) {
            $query->where('max_day_trip', '<=', $request->max_day_trip);
        }


        // ترتيب النتائج
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $cars = $query->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $cars,
            'applied_filters' => [
                'model_car_id' => $request->model_car_id ?? null,
                'year' => $request->year ?? null,
                'status' => $request->status ?? null,
                'price_range' => [
                    'min' => $request->min_price ?? null,
                    'max' => $request->max_price ?? null
                ],
                'trip_days_range' => [
                    'min' => $request->min_day_trip ?? null,
                    'max' => $request->max_day_trip ?? null
                ]
            ]
        ]);
    }


    /*public function storeCar(Request $request)
    {
        $adminUser = auth()->user();
        $isAdmin = $adminUser->type == 1;

        // تحديد المستخدم المستهدف
        $targetUserId = $isAdmin && $request->has('user_id') ? $request->user_id : $adminUser->id;
        $user = User::findOrFail($targetUserId);

        $userCarCount = Cars::where('owner_id', $user->id)->count();
        $isIndividualWithExistingCars = $user->is_company == 0 && $userCarCount > 0;

        // البحث عن خطط المستخدم النشطة
        $activePlan = $user->user_plan()->where('status', 'active')->first();

        // في جميع الحالات، يجب أن يكون لديه خطة نشطة
        if (!$activePlan) {
            return response()->json([
                'status' => false,
                'message' => 'يجب أن يكون لديك اشتراك نشط لإضافة سيارة'
            ], 422);
        }

        // التحقق من وجود سيارات متبقية في الخطة
        if ($activePlan->remaining_cars <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'لقد استنفذت عدد السيارات المسموحة في خطتك'
            ], 422);
        }

        $hasCompanyInfo = $user->company()->exists();

        $validationRules = [
            'make' => 'required|string|max:255',
            'model_id' => 'required|exists:car_models,id',
            'brand_id' => 'required|exists:brands,id',
            'year_id' => 'required|exists:model_years,id',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'vin' => 'required|string|size:17',
            'number' => 'required|string|max:50',
            'price' => 'required|numeric',
            'lat' => 'required',
            'lang' => 'required',
            'day' => 'required|integer|min:1',
            'image_license' => 'required|image|mimes:jpeg,png,jpg|max:2048',
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
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ];

        if ($isAdmin) {
            $validationRules['user_id'] = 'sometimes|exists:users,id';
        }

        // إضافة قواعد التحقق من بيانات الشركة إذا لزم الأمر
        if ($isIndividualWithExistingCars && !$hasCompanyInfo) {
            $companyRules = [
                'company.legal_name' => 'required|string|max:255',
                'company.num_of_employees' => 'required|integer',
                'company.is_under_vat' => 'required|boolean',
                'company.vat_num' => 'required_if:company.is_under_vat,true|string|max:255',
                'company.zip_code' => 'required|string|max:20',
                'company.country' => 'required|string|max:100',
                'company.address_1' => 'required|string|max:255',
                'company.address_2' => 'nullable|string|max:255',
                'company.city' => 'required|string|max:100',
                'company.image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ];
            $validationRules = array_merge($validationRules, $companyRules);
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // التحقق من العلاقات بين البراند والموديل والسنة
        try {
            // التحقق أن الموديل يتبع البراند
            $model = CarModel::where('id', $request->model_id)
                            ->where('brand_id', $request->brand_id)
                            ->first();

            if (!$model) {
                return response()->json([
                    'status' => false,
                    'message' => 'الموديل المحدد لا ينتمي إلى البراند المحدد'
                ], 422);
            }

            // التحقق أن السنة تتبع الموديل
            $year = ModelYear::where('id', $request->year_id)
                            ->where('car_model_id', $request->model_id)
                            ->first();

            if (!$year) {
                return response()->json([
                    'status' => false,
                    'message' => 'السنة المحددة لا تنتمي إلى الموديل المحدد'
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء التحقق من العلاقات: ' . $e->getMessage(),
            ], 500);
        }

        DB::beginTransaction();

        try {
            // خصم سيارة من العدد المتبقي في الخطة النشطة
            $activePlan->decrement('remaining_cars');

            // معالجة بيانات الشركة إذا لزم الأمر
            if ($isIndividualWithExistingCars && !$hasCompanyInfo) {
                $companyData = [
                    'legal_name' => $request->company['legal_name'],
                    'num_of_employees' => $request->company['num_of_employees'],
                    'is_under_vat' => $request->company['is_under_vat'],
                    'vat_num' => $request->company['vat_num'] ?? null,
                    'zip_code' => $request->company['zip_code'],
                    'country' => $request->company['country'],
                    'address_1' => $request->company['address_1'],
                    'address_2' => $request->company['address_2'] ?? null,
                    'city' => $request->company['city']
                ];

                // حفظ صورة الشركة إذا تم رفعها
                if ($request->hasFile('company.image')) {
                    $companyImage = $request->file('company.image');
                    $companyImageName = Str::random(32) . '.' . $companyImage->getClientOriginalExtension();
                    $companyImagePath = 'company_images/' . $companyImageName;
                    Storage::disk('public')->put($companyImagePath, file_get_contents($companyImage));
                    $companyData['image'] = url('api/storage/' . $companyImagePath);
                }

                $user->company()->create($companyData);
                $user->update(['is_company' => 1]);
            }

            // حفظ صورة الرخصة
            $licenseUrl = null;
            if ($request->hasFile('image_license')) {
                $licenseImage = $request->file('image_license');
                $licenseName = Str::random(32) . '.' . $licenseImage->getClientOriginalExtension();
                $licensePath = 'car_licenses/' . $licenseName;
                Storage::disk('public')->put($licensePath, file_get_contents($licenseImage));
                $licenseUrl = url('api/storage/' . $licensePath);
            }

            // الحصول على صورة السنة من ModelYear إذا كانت موجودة
            $externalImage = null;
            if ($year->image) {
                $externalImage = $year->image;
            }

            // بيانات إنشاء السيارة
            $carData = [
                'owner_id' => $user->id,
                'make' => $request->make,
                'car_model_id' => $request->model_id,
                'brand_id' => $request->brand_id,
                'model_year_id' => $request->year_id,
                'extenal_image' => $externalImage, // إضافة صورة السنة هنا
                'price' => $request->price,
                'day' => $request->day,
                'lang' => $request->lang,
                'lat' => $request->lat,
                'address' => $request->address,
                'description' => $request->description,
                'number' => $request->number,
                'vin' => $request->vin,
                'image_license' => $licenseUrl,
                'number_license' => $request->number_license,
                'state' => $request->state,
                'description_condition' => $request->description_condition,
                'advanced_notice' => $request->advanced_notice,
                'min_day_trip' => $request->min_day_trip,
                'max_day_trip' => $request->max_day_trip,
                'is_paid' => 1, // دائماً مدفوعة لأنها ضمن الخطة
                'status' => $isAdmin ? 'active' : 'active', // الحالة active مباشرة
                'user_plan_id' => $activePlan->id // ربط السيارة بالخطة النشطة
            ];

            // إنشاء السيارة
            $car = Cars::create($carData);

            // تحديث حالة المستخدم إذا كانت أول سيارة
            if ($userCarCount == 0) {
                $user->update(['has_car' => 1]);
            }

            // حفظ مميزات السيارة
            if ($request->has('features')) {
                $car->cars_features()->create([
                    'mileage_range' => $request->features['mileage_range'] ?? null,
                    'transmission' => $request->features['transmission'] ?? null,
                    'mechanical_condition' => $request->features['mechanical_condition'] ?? null,
                    'all_have_seatbelts' => $request->features['all_have_seatbelts'] ?? false,
                    'num_of_door' => $request->features['num_of_door'] ?? null,
                    'num_of_seat' => $request->features['num_of_seat'] ?? null,
                    'additional_features' => $request->features['additional_features'] ?? [],
                ]);
            }

            // حفظ صور السيارة
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                    $imagePath = 'car_images/' . $imageName;
                    Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                    $imageUrl = url('api/storage/' . $imagePath);

                    Cars_Image::create([
                        'cars_id' => $car->id,
                        'image' => $imageUrl,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم إنشاء السيارة بنجاح وخصمها من خطتك',
                'data' => $car->load(['cars_features', 'car_image', 'user_plan', 'brand', 'model', 'years'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StoreCar failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء حفظ السيارة: ' . $e->getMessage(),
            ], 500);
        }
    }*/


    public function storeCar(Request $request)
    {
        $adminUser = auth()->user();
        $isAdmin = $adminUser->type == 1;

        // تحديد المستخدم المستهدف
        $targetUserId = $isAdmin && $request->has('user_id') ? $request->user_id : $adminUser->id;
        $user = User::findOrFail($targetUserId);

        $userCarCount = Cars::where('owner_id', $user->id)->count();
        $isIndividualWithExistingCars = $user->is_company == 0 && $userCarCount > 0;

        // التحقق من وجود user_plan_id في الطلب
        if (!$request->has('user_plan_id')) {
            return response()->json([
                'status' => false,
                'message' => 'يجب تحديد معرف الخطة (user_plan_id)'
            ], 422);
        }

        // البحث عن الخطة المحددة والتأكد أنها تعود للمستخدم
        $activePlan = User_Plan::where('id', $request->user_plan_id)
                            ->where('user_id', $user->id)
                            ->first();

        // التأكد من وجود الخطة وأنها نشطة
        if (!$activePlan) {
            return response()->json([
                'status' => false,
                'message' => 'الخطة المحددة غير موجودة أو لا تنتمي لهذا المستخدم'
            ], 422);
        }

        if ($activePlan->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'الخطة المحددة غير نشطة'
            ], 422);
        }

        // التحقق من وجود سيارات متبقية في الخطة
        if ($activePlan->remaining_cars <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'لقد استنفذت عدد السيارات المسموحة في خطتك'
            ], 422);
        }

        $hasCompanyInfo = $user->company()->exists();

        $validationRules = [
            'user_plan_id' => 'required|exists:user__plans,id', // إضافة قاعدة التحقق
            'make' => 'required|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'vin' => 'required|string|size:17',
            'number' => 'required|string|max:50',
            'price' => 'required|numeric',
            'lat' => 'required',
            'lang' => 'required',
            'day' => 'required|integer|min:1',
            'image_license' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'number_license' => 'required|string|size:17',
            'state' => 'required|string|max:100',
            'description_condition' => 'nullable|string',
            'advanced_notice' => 'nullable|string|max:10',
            'min_day_trip' => 'nullable|integer',
            'max_day_trip' => 'nullable|integer',
            'driver_available' => 'required|boolean', // إضافة التحقق من driver_available
            'features.mileage_range' => 'nullable|string',
            'features.transmission' => 'nullable|in:automatic,manual',
            'features.mechanical_condition' => 'nullable|in:good,not_working,excellent',
            'features.all_have_seatbelts' => 'nullable|boolean',
            'features.num_of_door' => 'nullable|integer',
            'features.num_of_seat' => 'nullable|integer',
            'features.additional_features' => 'array',
            'images' => 'required|array',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
        ];

        // قواعد التحقق المرنة للبراند والموديل والسنة
        if ($request->has('brand_id')) {
            $validationRules['brand_id'] = 'required|exists:brands,id';

            // إذا أرسل brand_id فقط، يجب إرسال model_name
            if (!$request->has('model_id')) {
                $validationRules['model_name'] = 'required|string|max:255';
            } else {
                $validationRules['model_id'] = 'required|exists:car_models,id';
            }

            // إذا أرسل model_id، يجب إرسال year_id أو year_value
            if ($request->has('model_id')) {
                if (!$request->has('year_id')) {
                    $validationRules['year_value'] = 'required|integer|min:1900|max:' . (date('Y') + 1);
                } else {
                    $validationRules['year_id'] = 'required|exists:model_years,id';
                }
            } else {
                // إذا لم يرسل model_id، يجب إرسال year_value
                $validationRules['year_value'] = 'required|integer|min:1900|max:' . (date('Y') + 1);
            }
        } else {
            // إذا لم يتم إرسال brand_id، يجب إرسال الأسماء الجديدة
            $validationRules['brand_name'] = 'required|string|max:255';
            $validationRules['model_name'] = 'required|string|max:255';
            $validationRules['year_value'] = 'required|integer|min:1900|max:' . (date('Y') + 1);
        }

        if ($isAdmin) {
            $validationRules['user_id'] = 'sometimes|exists:users,id';
        }

        // إضافة قواعد التحقق من بيانات الشركة إذا لزم الأمر
        if ($isIndividualWithExistingCars && !$hasCompanyInfo) {
            $companyRules = [
                'company.legal_name' => 'required|string|max:255',
                'company.num_of_employees' => 'required|integer',
                'company.is_under_vat' => 'required|boolean',
                'company.vat_num' => 'required_if:company.is_under_vat,true|string|max:255',
                'company.zip_code' => 'required|string|max:20',
                'company.country' => 'required|string|max:100',
                'company.address_1' => 'required|string|max:255',
                'company.address_2' => 'nullable|string|max:255',
                'company.city' => 'required|string|max:100',
                'company.image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ];
            $validationRules = array_merge($validationRules, $companyRules);
        }

        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $brandId = null;
            $modelId = null;
            $yearId = null;
            $externalImage = null;

            // المعالجة بناءً على البيانات المرسلة
            if ($request->has('brand_id')) {
                $brandId = $request->brand_id;

                // معالجة الموديل
                if ($request->has('model_id')) {
                    // استخدام الموديل الموجود
                    $modelId = $request->model_id;

                    // التحقق من العلاقة بين البراند والموديل
                    $model = CarModel::where('id', $modelId)
                                    ->where('brand_id', $brandId)
                                    ->first();

                    if (!$model) {
                        throw new \Exception('الموديل المحدد لا ينتمي إلى البراند المحدد');
                    }
                } else {
                    // إنشاء موديل جديد وربطه بالبراند الموجود
                    $model = CarModel::create([
                        'brand_id' => $brandId,
                        'name' => $request->model_name
                    ]);
                    $modelId = $model->id;
                }

                // معالجة السنة
                if ($request->has('year_id')) {
                    // استخدام السنة الموجودة
                    $yearId = $request->year_id;

                    // التحقق من العلاقة بين الموديل والسنة
                    $year = ModelYear::where('id', $yearId)
                                    ->where('car_model_id', $modelId)
                                    ->first();

                    if (!$year) {
                        throw new \Exception('السنة المحددة لا تنتمي إلى الموديل المحدد');
                    }

                    // الحصول على صورة السنة إذا كانت موجودة
                    if ($year->image) {
                        $externalImage = $year->image;
                    }
                } else {
                    // إنشاء سنة جديدة وربطها بالموديل
                    $year = ModelYear::create([
                        'car_model_id' => $modelId,
                        'year' => $request->year_value
                    ]);
                    $yearId = $year->id;
                }

            } else {
                // إنشاء براند جديد
                $brand = Brand::create([
                    'name' => $request->brand_name,
                    'make_id' => Str::slug($request->brand_name),
                    'country' => 'Unknown'
                ]);
                $brandId = $brand->id;

                // إنشاء موديل جديد
                $model = CarModel::create([
                    'brand_id' => $brandId,
                    'name' => $request->model_name
                ]);
                $modelId = $model->id;

                // إنشاء سنة جديدة
                $year = ModelYear::create([
                    'car_model_id' => $modelId,
                    'year' => $request->year_value
                ]);
                $yearId = $year->id;
            }

            // خصم سيارة من العدد المتبقي في الخطة المحددة
            $activePlan->decrement('remaining_cars');

            // معالجة بيانات الشركة إذا لزم الأمر
            if ($isIndividualWithExistingCars && !$hasCompanyInfo) {
                $companyData = [
                    'legal_name' => $request->company['legal_name'],
                    'num_of_employees' => $request->company['num_of_employees'],
                    'is_under_vat' => $request->company['is_under_vat'],
                    'vat_num' => $request->company['vat_num'] ?? null,
                    'zip_code' => $request->company['zip_code'],
                    'country' => $request->company['country'],
                    'address_1' => $request->company['address_1'],
                    'address_2' => $request->company['address_2'] ?? null,
                    'city' => $request->company['city']
                ];

                // حفظ صورة الشركة إذا تم رفعها
                if ($request->hasFile('company.image')) {
                    $companyImage = $request->file('company.image');
                    $companyImageName = Str::random(32) . '.' . $companyImage->getClientOriginalExtension();
                    $companyImagePath = 'company_images/' . $companyImageName;
                    Storage::disk('public')->put($companyImagePath, file_get_contents($companyImage));
                    $companyData['image'] = url('api/storage/' . $companyImagePath);
                }

                $user->company()->create($companyData);
                $user->update(['is_company' => 1]);
            }

            // حفظ صورة الرخصة
            $licenseUrl = null;
            if ($request->hasFile('image_license')) {
                $licenseImage = $request->file('image_license');
                $licenseName = Str::random(32) . '.' . $licenseImage->getClientOriginalExtension();
                $licensePath = 'car_licenses/' . $licenseName;
                Storage::disk('public')->put($licensePath, file_get_contents($licenseImage));
                $licenseUrl = url('api/storage/' . $licensePath);
            }

            // بيانات إنشاء السيارة
            $carData = [
                'owner_id' => $user->id,
                'make' => $request->make,
                'car_model_id' => $modelId,
                'brand_id' => $brandId,
                'model_year_id' => $yearId,
                'extenal_image' => $externalImage,
                'price' => $request->price,
                'day' => $request->day,
                'lang' => $request->lang,
                'lat' => $request->lat,
                'address' => $request->address,
                'description' => $request->description,
                'number' => $request->number,
                'vin' => $request->vin,
                'image_license' => $licenseUrl,
                'number_license' => $request->number_license,
                'state' => $request->state,
                'description_condition' => $request->description_condition,
                'advanced_notice' => $request->advanced_notice,
                'min_day_trip' => $request->min_day_trip,
                'max_day_trip' => $request->max_day_trip,
                'driver_available' => $request->driver_available, // إضافة driver_available
                'is_paid' => 1,
                'status' => $isAdmin ? 'pending' : 'pending',
                'user_plan_id' => $activePlan->id // استخدام الخطة المحددة
            ];

            // إنشاء السيارة
            $car = Cars::create($carData);

            // تحديث حالة المستخدم إذا كانت أول سيارة
            if ($userCarCount == 0) {
                $user->update(['has_car' => 1]);
            }

            // حفظ مميزات السيارة
            if ($request->has('features')) {
                $car->cars_features()->create([
                    'mileage_range' => $request->features['mileage_range'] ?? null,
                    'transmission' => $request->features['transmission'] ?? null,
                    'mechanical_condition' => $request->features['mechanical_condition'] ?? null,
                    'all_have_seatbelts' => $request->features['all_have_seatbelts'] ?? false,
                    'num_of_door' => $request->features['num_of_door'] ?? null,
                    'num_of_seat' => $request->features['num_of_seat'] ?? null,
                    'additional_features' => $request->features['additional_features'] ?? [],
                ]);
            }

            // حفظ صور السيارة
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                    $imagePath = 'car_images/' . $imageName;
                    Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                    $imageUrl = url('api/storage/' . $imagePath);

                    Cars_Image::create([
                        'cars_id' => $car->id,
                        'image' => $imageUrl,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم إنشاء السيارة بنجاح وخصمها من خطتك',
                'data' => $car->load(['cars_features', 'car_image', 'user_plan', 'brand', 'model', 'years'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('StoreCar failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء حفظ السيارة: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateCar(Request $request, $id)
    {
        $adminUser = auth()->user();
        $isAdmin = $adminUser->type == 1;

        DB::beginTransaction();

        try {
            // بناء استعلام جلب السيارة
            $carQuery = Cars::where('id', $id);
            // إذا لم يكن المستخدم admin نضيف شرط owner_id
            if (!$isAdmin) {
                $carQuery->where('owner_id', $adminUser->id);
            }

            $car = $carQuery->first();

            if (!$car) {
                return response()->json([
                    'status' => false,
                    'message' => 'السيارة غير موجودة أو لا تملك صلاحية التعديل.',
                ], 404);
            }

            // التحقق من أن حالة السيارة تسمح بالتعديل
            $allowedStatuses = ['pending', 'active', 'rejected'];
              if (!in_array($car->status, $allowedStatuses)) {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن تعديل السيارة في حالتها الحالية.',
                ], 422);
            }

            // التحقق من أن الاشتراك الخاص بالسيارة نشط
            if (!$car->user_plan || $car->user_plan->status !== 'active') {
                return response()->json([
                    'status' => false,
                    'message' => 'يجب أن يكون الاشتراك الخاص بهذه السيارة نشطاً لتعديلها'
                ], 422);
            }

            // التحقق من وجود حجوزات نشطة للسيارة
            $hasActiveBookings = Order_Booking::where('car_id', $car->id)
                ->where(function($query) {
                    $query->whereIn('status', ['pending', 'picked_up', 'Returned'])
                        ->orWhere(function($q) {
                            $q->where('status', 'Completed')
                                ->where('completed_at', '>=', now()->subHours(12));
                        });
                })
                ->exists();

            if ($hasActiveBookings) {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن تعديل السيارة لأن لديها حجوزات نشطة'
                ], 422);
            }

            $user = User::findOrFail($car->owner_id);

            $validationRules = [
                'make' => 'sometimes|string|max:255',
                'model_id' => 'sometimes|exists:car_models,id',
                'brand_id' => 'sometimes|exists:brands,id',
                'year_id' => 'sometimes|exists:model_years,id',
                'description' => 'sometimes|string|nullable',
                'address' => 'sometimes|string|nullable',
                'vin' => 'sometimes|string|size:17',
                'number' => 'sometimes|string|max:50',
                'price' => 'sometimes|numeric',
                'lat' => 'sometimes',
                'lang' => 'sometimes',
                'day' => 'sometimes|integer|min:1',
                'image_license' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
                'number_license' => 'sometimes|string|size:17',
                'state' => 'sometimes|string|max:100',
                'description_condition' => 'sometimes|string|nullable',
                'advanced_notice' => 'sometimes|string|max:10|nullable',
                'min_day_trip' => 'sometimes|integer|nullable',
                'max_day_trip' => 'sometimes|integer|nullable',
                'driver_available' => 'sometimes|boolean',
                'features.mileage_range' => 'sometimes|string|nullable',
                'features.transmission' => 'sometimes|in:automatic,manual|nullable',
                'features.mechanical_condition' => 'sometimes|in:good,not_working,excellent|nullable',
                'features.all_have_seatbelts' => 'sometimes|boolean|nullable',
                'features.num_of_door' => 'sometimes|integer|nullable',
                'features.num_of_seat' => 'sometimes|integer|nullable',
                'features.additional_features' => 'sometimes|array|nullable',
                'images' => 'sometimes|array',
                'images.*' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
            ];

            if ($isAdmin) {
                $validationRules['user_id'] = 'sometimes|exists:users,id';
                $validationRules['status'] = 'sometimes|in:pending,active,rejected,reupload'; // إضافة reupload
            }

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // التحقق من العلاقات بين البراند والموديل والسنة إذا تم إرسالها
            if ($request->has('model_id') || $request->has('brand_id') || $request->has('year_id')) {
                $modelId = $request->model_id ?? $car->car_model_id;
                $brandId = $request->brand_id ?? $car->brand_id;
                $yearId = $request->year_id ?? $car->model_year_id;

                // التحقق أن الموديل يتبع البراند
                if ($request->has('model_id') || $request->has('brand_id')) {
                    $model = CarModel::where('id', $modelId)
                                    ->where('brand_id', $brandId)
                                    ->first();

                    if (!$model) {
                        return response()->json([
                            'status' => false,
                            'message' => 'الموديل المحدد لا ينتمي إلى البراند المحدد'
                        ], 422);
                    }
                }

                // التحقق أن السنة تتبع الموديل
                if ($request->has('year_id') || $request->has('model_id')) {
                    $year = ModelYear::where('id', $yearId)
                                    ->where('car_model_id', $modelId)
                                    ->first();

                    if (!$year) {
                        return response()->json([
                            'status' => false,
                            'message' => 'السنة المحددة لا تنتمي إلى الموديل المحدد'
                        ], 422);
                    }
                }
            }

            $data = $request->all();

            // التحقق من وجود صورة الرخصة ثم حفظها بنفس طريقة المشروع
            if ($request->hasFile('image_license')) {
                $licenseImage = $request->file('image_license');
                $licenseName = Str::random(32) . '.' . $licenseImage->getClientOriginalExtension();
                $licensePath = 'car_licenses/' . $licenseName;
                Storage::disk('public')->put($licensePath, file_get_contents($licenseImage));
                $data['image_license'] = url('api/storage/' . $licensePath);
            }

            // إذا تم تحديث year_id، جلب الصورة الجديدة من ModelYear
            if ($request->has('year_id')) {
                $newYear = ModelYear::find($request->year_id);
                if ($newYear && $newYear->image) {
                    $data['extenal_image'] = $newYear->image;
                } else {
                    $data['extenal_image'] = null;
                }
            }

            // تحديث بيانات السيارة
            $updateData = [];
            $fields = [
                'make', 'owner_id', 'car_model_id', 'brand_id', 'model_year_id',
                'extenal_image', 'price', 'day', 'lang', 'lat',
                'address', 'description', 'number', 'vin', 'image_license',
                'number_license', 'state', 'description_condition', 'advanced_notice',
                'min_day_trip', 'max_day_trip', 'driver_available'
            ];

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // تحديد الحالة الجديدة بناءً على الحالة الحالية
            if (!$isAdmin) {
                // للمستخدم العادي
                if (in_array($car->status, ['pending', 'active'])) {
                    $updateData['status'] = 'pending';
                } elseif ($car->status === 'rejected') {
                    $updateData['status'] = 'reupload';
                }
            } else {
                // للإدمن - السماح بتعديل الحالة يدوياً إذا تم إرسالها
                if ($request->has('status')) {
                    $updateData['status'] = $request->status;
                }
            }

            $car->update($updateData);

            // تحديث بيانات المزايا إن وُجدت
            if ($request->has('features')) {
                $featuresData = [
                    'mileage_range' => $request->features['mileage_range'] ?? null,
                    'transmission' => $request->features['transmission'] ?? null,
                    'mechanical_condition' => $request->features['mechanical_condition'] ?? null,
                    'all_have_seatbelts' => $request->features['all_have_seatbelts'] ?? null,
                    'num_of_door' => $request->features['num_of_door'] ?? null,
                    'num_of_seat' => $request->features['num_of_seat'] ?? null,
                    'additional_features' => $request->features['additional_features'] ?? [],
                ];

                if ($car->cars_features) {
                    $car->cars_features()->update($featuresData);
                } else {
                    $car->cars_features()->create($featuresData);
                }
            }

            // إذا تم استقبال صور جديدة، نحذف القديمة ونضيف الجديدة
            if ($request->hasFile('images')) {
                // حذف جميع الصور القديمة للسيارة
                $oldImages = Cars_Image::where('cars_id', $car->id)->get();

                foreach ($oldImages as $oldImage) {
                    // حذف الملف الفعلي من التخزين
                    $oldImagePath = str_replace(url('api/storage/'), '', $oldImage->image);
                    if (Storage::disk('public')->exists($oldImagePath)) {
                        Storage::disk('public')->delete($oldImagePath);
                    }
                    // حذف السجل من قاعدة البيانات
                    $oldImage->delete();
                }

                // إضافة الصور الجديدة بنفس طريقة المشروع
                foreach ($request->file('images') as $imageFile) {
                    $imageName = Str::random(32) . '.' . $imageFile->getClientOriginalExtension();
                    $imagePath = 'car_images/' . $imageName;
                    Storage::disk('public')->put($imagePath, file_get_contents($imageFile));

                    $imageUrl = url('api/storage/' . $imagePath);

                    Cars_Image::create([
                        'cars_id' => $car->id,
                        'image' => $imageUrl,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم تحديث السيارة بنجاح.' . (!$isAdmin ? ' وتم إرسالها للمراجعة.' : ''),
                'data' => $car->load(['cars_features', 'car_image', 'brand', 'model', 'years', 'user_plan']),
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
        Log::info('Received car features update request', [
            'request_data' => $request->all()
        ]);

        DB::beginTransaction();

        try {
            $user = auth()->user();

            // بناء الاستعلام الأساسي
            $carQuery = Cars::where('id', $car_id);

            // إذا لم يكن المستخدم admin نضيف شرط owner_id
            if ($user->type != 1) {
                $carQuery->where('owner_id', $user->id);
            }

            $car = $carQuery->first();

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

        if ($car->is_rented != 0) {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن حذف سيارة مستأجرة',
            ], 400); // تغيير كود الخطأ إلى 400 لأنه خطأ في الطلب
        }

        $user = auth()->user();

        // السماح للمسؤول بحذف أي سيارة أو للمالك بحذف سيارته
        if ($user->type != 1 && $user->id != $car->owner_id) {
            return response()->json([
                'status' => false,
                'message' => 'هذه السيارة ليست ملكك',
            ], 403); // كود 403 للوصول الممنوع
        }

        $car->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف السيارة بنجاح.',
        ]);
    }



    public function updateCarStatus(Request $request, $id)
    {
        $adminUser = auth()->user();
        $isAdmin = $adminUser->type == 1;

        try {
            // بناء استعلام جلب السيارة
            $carQuery = Cars::where('id', $id);

            $car = $carQuery->first();

            if (!$car) {
                return response()->json([
                    'status' => false,
                    'message' => 'السيارة غير موجودة.',
                ], 404);
            }

            // قواعد التحقق
            $validationRules = [
                'status' => 'required|in:active,inactive,rejected'
            ];

            $validator = Validator::make($request->all(), $validationRules);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // التحقق من الشروط الإضافية للإدمن
            // إذا أراد الإدمن تغيير الحالة إلى active
            if ($request->status == 'active') {
                // التحقق من أن الاشتراك الخاص بالسيارة نشط
                if (!$car->user_plan || $car->user_plan->status !== 'active') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تفعيل السيارة لأن الاشتراك الخاص بها غير نشط'
                    ], 422);
                }

                // التحقق من حالة السيارة الحالية
                if (!in_array($car->status, ['pending', 'inactive','reupload'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'يمكن تفعيل السيارة فقط إذا كانت حالتها pending أو inactive أو reupload'
                    ], 422);
                }
            }

            // إذا أراد الإدمن تغيير الحالة إلى inactive
            if ($request->status == 'inactive') {
                // التحقق من حالة السيارة الحالية
                if ($car->status !== 'active') {
                    return response()->json([
                        'status' => false,
                        'message' => 'يمكن تعطيل السيارة فقط إذا كانت حالتها active'
                    ], 422);
                }

                // التحقق من عدم وجود حجوزات نشطة للسيارة
                $hasActiveBookings = Order_Booking::where('car_id', $car->id)
                    ->where(function($query) {
                        $query->whereIn('status', ['pending', 'picked_up', 'Returned'])
                            ->orWhere(function($q) {
                                $q->where('status', 'Completed')
                                    ->where('completed_at', '>=', now()->subHours(12));
                            });
                    })
                    ->exists();

                if ($hasActiveBookings) {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تعطيل السيارة لأن لديها حجوزات نشطة'
                    ], 422);
                }
            }

            // إذا أراد الإدمن تغيير الحالة إلى rejected
            if ($request->status == 'rejected') {
                // التحقق من حالة السيارة الحالية - السماح برفض السيارة إذا كانت pending أو reupload
                $allowedStatusesForRejection = ['pending', 'reupload'];
                if (!in_array($car->status, $allowedStatusesForRejection)) {
                    return response()->json([
                        'status' => false,
                        'message' => 'يمكن رفض السيارة فقط إذا كانت حالتها pending أو reupload'
                    ], 422);
                }

                // التحقق من إرسال أسباب الرفض
                if (!$request->has('rejection_reasons') || !is_array($request->rejection_reasons) || count($request->rejection_reasons) === 0) {
                    return response()->json([
                        'status' => false,
                        'message' => 'يجب إرسال مصفوفة أسباب الرفض'
                    ], 422);
                }
            }

            DB::beginTransaction();

            // تحديث حالة السيارة
            $car->update([
                'status' => $request->status
            ]);

            // إذا كانت الحالة rejected، نقوم بحفظ أسباب الرفض
            if ($request->status === 'rejected' && $request->has('rejection_reasons')) {
                // حذف أسباب الرفض القديمة إذا وجدت
                $car->rejectionReasons()->delete();
                // حفظ أسباب الرفض الجديدة
                foreach ($request->rejection_reasons as $reason) {
                    RejectionReason::create([
                        'car_id' => $car->id,
                        'reason' => $reason,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'تم تحديث حالة السيارة بنجاح.',
                'data' => $car->fresh()->load('rejectionReasons')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('UpdateCarStatus failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تحديث حالة السيارة.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



     public function getRejectionReasons(Request $request, $id)
    {
        try {
            // التحقق من صحة الـ ID
            $validator = Validator::make(['id' => $id], [
                'id' => 'required|integer|exists:cars,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'معرف السيارة غير صالح',
                    'errors' => $validator->errors()
                ], 422);
            }

            // جلب السيارة
            $car = Cars::find($id);

            if (!$car) {
                return response()->json([
                    'status' => false,
                    'message' => 'السيارة غير موجودة'
                ], 404);
            }

            // التحقق من أن المستخدم الحالي هو صاحب السيارة
            $user = auth()->user();
            if ($car->owner_id !== $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'غير مصرح لك بالوصول إلى أسباب الرفض لهذه السيارة'
                ], 403);
            }

            // التحقق من أن حالة السيارة هي rejected
            if ($car->status !== 'rejected') {
                return response()->json([
                    'status' => false,
                    'message' => 'حالة السيارة ليست مرفوضة'
                ], 422);
            }

            // جلب أسباب الرفض
            $rejectionReasons = RejectionReason::where('car_id', $car->id)
                ->get()
                ->pluck('reason');

            return response()->json([
                'status' => true,
                'message' => 'تم جلب أسباب الرفض بنجاح',
                'data' => [
                    'car_id' => $car->id,
                    'car_status' => $car->status,
                    'rejection_reasons' => $rejectionReasons
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب أسباب الرفض',
                'error' => $e->getMessage()
            ], 500);
        }
    }



}














