<?php

namespace App\Http\Controllers;

use App\Models\Driver_Price;
use App\Models\Review;
use App\Models\Driver_license;
use App\Models\User;
use App\Models\Order_Booking;
use App\Models\Cars;
use App\Models\Represen_Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderBookingController extends Controller
{




public function store(Request $request, $id)
{
    $user = auth()->user();
    $request['user_id'] = $user->id;
    $request['car_id'] = $id;

    // Define all validation rules
    $validationRules = [
        'user_id' => 'required|exists:users,id',
        'car_id' => 'required|exists:cars,id',
        'date_from' => 'required|date|after_or_equal:today',
        'date_end' => 'required|date|after:date_from',
        'country' => 'nullable|string|max:255',
        'state' => 'nullable|string|max:255',
        'first_name' => 'nullable|string|max:255',
        'last_name' => 'nullable|string|max:255',
        'birth_date' => 'nullable|string|max:255',
        'with_driver' => 'required|boolean',
        'expiration_date' => 'nullable|date|after_or_equal:today',
        'number' => 'nullable|numeric',
        'payment_method' => 'required|string|in:visa,cash',
        'driver_type' => 'required|string|in:pick_up,mail_in',
    ];

    // Add conditional validation based on delivery_type
    if ($request->driver_type == 'pick_up') {
        $validationRules['station_id'] = 'required|exists:stations,id';
    } elseif ($request->driver_type == 'mail_in') {
        $validationRules['lat'] = 'required|numeric';
        $validationRules['lang'] = 'required|numeric';
    }

    // Validate the request
    $validator = Validator::make($request->all(), $validationRules);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422);
    }

    // Check car availability
    $car = Cars::find($request->car_id);
    if (!$car || $car->status !== 'active' || $car->is_rented == 1) {
        return response()->json([
            'status' => false,
            'message' => 'Car is not available for booking',
        ], 404);
    }

    // Validate booking dates
    $dateFrom = Carbon::parse($request->date_from);
    $dateEnd = Carbon::parse($request->date_end);
    $days = $dateFrom->diffInDays($dateEnd);

    if ($days < $car->min_day_trip) {
        return response()->json([
            'status' => false,
            'message' => 'Booking duration is less than minimum allowed: ' . $car->min_day_trip . ' days',
        ], 422);
    }

    if (!is_null($car->max_day_trip) && $days > $car->max_day_trip) {
        return response()->json([
            'status' => false,
            'message' => 'Booking duration exceeds maximum allowed: ' . $car->max_day_trip . ' days',
        ], 422);
    }

    // Check for overlapping bookings
    $overlappingBooking = Order_Booking::where('car_id', $request->car_id)
        ->where('status', '!=', 'Finished')
        ->where(function ($query) use ($dateFrom, $dateEnd) {
            $query->whereDate('date_from', '<=', $dateEnd)
                ->whereDate('date_end', '>=', $dateFrom);
        })
        ->exists();

    if ($overlappingBooking) {
        return response()->json([
            'status' => false,
            'message' => 'Car is already booked for the selected dates',
        ], 422);
    }

    // Calculate total price
    $total_price = $car->price * $days;

    if ($request->with_driver) {
        $driver_price = Driver_Price::first();
        if (!$driver_price) {
            return response()->json([
                'status' => false,
                'message' => 'Driver price not configured',
            ], 422);
        }
        $total_price += $driver_price->price;
    }

    DB::beginTransaction();

    try {
        // Prepare booking data
        $bookingData = [
            'user_id' => $request->user_id,
            'car_id' => $request->car_id,
            'date_from' => $request->date_from,
            'date_end' => $request->date_end,
            'with_driver' => $request->with_driver,
            'total_price' => $total_price,
            'payment_method' => $request->payment_method,
            'driver_type' => $request->driver_type,
        ];

        // Add location data based on delivery type
        if ($request->driver_type == 'pick_up') {
            $bookingData['station_id'] = $request->station_id;
        } elseif ($request->driver_type == 'mail_in') {
            $bookingData['lat'] = $request->lat;
            $bookingData['lang'] = $request->lang;
        }

        // Create the booking
        $booking = Order_Booking::create($bookingData);

        // Handle driver license if needed
        if ($user->has_license == 0) {
            $existing_license = Driver_license::where('user_id', $user->id)
                ->where('number', $request->number)
                ->first();

            if ($existing_license) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'License number already exists',
                ], 422);
            }

            $licenseData = [
                'user_id' => $user->id,
                'country' => $request->country,
                'state' => $request->state,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'birth_date' => $request->birth_date,
                'expiration_date' => $request->expiration_date,
                'number' => $request->number,

            ];

            if ($request->hasFile('image_license')) {
                $path = $request->file('image_license')->store('licenses', 'public');
                $licenseData['image'] = $path;
            }

            Driver_license::create($licenseData);
            $user->has_license = 1;
            $user->save();
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Booking created successfully',
            'data' => $booking->load(['car', 'user']),
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Booking creation failed: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Failed to create booking',
            'error' => $e->getMessage()
        ], 500);
    }
}


    public function myBooking(Request $request)
    {
        $user = auth()->user();

        // بناء استعلام الحجوزات حسب الفلاتر
        $query = Order_Booking::where('user_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('date_from', '>=', Carbon::parse($request->date_from));
        }

        if ($request->has('date_end')) {
            $query->whereDate('date_end', '<=', Carbon::parse($request->date_end));
        }

        if ($request->has('car_id')) {
            $query->where('car_id', $request->car_id);
        }

        // تنفيذ الاستعلام وجلب النتائج
        $bookings = $query->with([
            'car_details',
            'car_details.car_image',
        ])->orderBy('date_from', 'desc')->get();

        // حساب الإحصائيات بدون التأثر بالفلاتر مثل status
        $baseStatsQuery = Order_Booking::where('user_id', $user->id);

        if ($request->has('date_from')) {
            $baseStatsQuery->whereDate('date_from', '>=', Carbon::parse($request->date_from));
        }

        if ($request->has('date_end')) {
            $baseStatsQuery->whereDate('date_end', '<=', Carbon::parse($request->date_end));
        }

        if ($request->has('car_id')) {
            $baseStatsQuery->where('car_id', $request->car_id);
        }

        // حساب meta
        $meta = [
            'total' => (clone $baseStatsQuery)->count(),

            'pending' => (clone $baseStatsQuery)->where('status', 'pending')->count(),

            'Confiremed' => (clone $baseStatsQuery)->where('status', 'Confiremed')->count(),
            'picked_up' => (clone $baseStatsQuery)->where('status', 'picked_up')->count(),
            'Returned' => (clone $baseStatsQuery)->where('status', 'Returned')->count(),
            'Completed' => (clone $baseStatsQuery)->where('status', 'Completed')->count(),
            'Canceled' => (clone $baseStatsQuery)->where('status', 'Canceled')->count(),
        ];

        return response()->json([
            'status' => true,
            'data' => $bookings,
            'meta' => $meta,
        ]);
    }

    public function my_order(Request $request)
    {
        $user = auth()->user();

        // جلب جميع سيارات المستخدم الحالي
        $myCarIds = Cars::where('owner_id', $user->id)->pluck('id');

        // استعلام الحجوزات حسب سيارات المستخدم
        $query = Order_Booking::whereIn('car_id', $myCarIds);

        // فلترة حسب حالة الحجز
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // فلترة حسب السعر
        if ($request->has('total_price')) {
            $query->where('total_price', $request->total_price);
        }

        // فلترة حسب المستأجر (user_id)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // فلترة حسب car_id بشرط أن تكون من سيارات المالك فقط
        if ($request->has('car_id')) {
            if ($myCarIds->contains($request->car_id)) {
                $query->where('car_id', $request->car_id);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'السيارة غير تابعة لك',
                ], 403);
            }
        }

        // فلترة حسب تاريخ البداية
        if ($request->has('date_from')) {
            $query->whereDate('date_from', '>=', Carbon::parse($request->date_from));
        }

        // فلترة حسب تاريخ النهاية
        if ($request->has('date_end')) {
            $query->whereDate('date_end', '<=', Carbon::parse($request->date_end));
        }

        // تنفيذ الاستعلام وجلب النتائج
        $orders = $query->with([
            'car_details',
            'user', // المستأجر
        ])->orderBy('date_from', 'desc')->get();

        return response()->json([
            'status' => true,
            'data' => $orders,
        ]);
    }

    public function show($id)
    {
        $user = auth()->user();
        $booking = Order_Booking::with(['car_details', 'car_details.car_image', 'payment'])->find($id);

        if (!$booking || $booking->user_id != $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود.او غير تابع لك',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $booking,
        ]);
    }

    public function show_my_order($id)
    {
        $user = auth()->user();

        // Get all car IDs owned by the user
        $myCarIds = Cars::where('owner_id', $user->id)->pluck('id');

        // Find the booking with relationships
        $booking = Order_Booking::with([
                'car_details',
                'car_details.car_image',
                'user',
                'payment'
            ])
            ->find($id);

        // Check if booking exists and belongs to user's cars
        if (!$booking || !$myCarIds->contains($booking->car_id)) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود أو السيارة غير تابعة لك',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $booking,
        ]);
    }


    public function update_status(Request $request, $id)
    {

        $validate = Validator::make(request()->all(), [

            'status' => 'required|in:pending,approver,rejected',
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validate->errors(),
            ], 422);
        }

        $booking = Order_Booking::find($id);
        if (!$booking || $booking->user_id != auth()->user()->id || $booking->status == $request->status) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود.او غير تابع لك او تم تحديث حالة الحجز بنجاح',
            ], 404);
        }


        $booking->status = $request->status;
        $booking->save();

        if ($request->status == 'approver') {
            $car = Cars::find($booking->car_id);
            $car->is_rented = 1;
            $car->save();

            $all_booking = Order_Booking::where('car_id', $booking->car_id)->where('status', 'pending')->get();

            foreach ($all_booking as $booking) {
                $booking->status = 'rejected';
                $booking->save();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الحجز بنجاح',
            'data' => $booking,
        ]);
    }




    public function calendar($id)
    {
        $bookings = Order_Booking::where('car_id', $id)
            ->where('status', '!=', 'Finished')
            ->whereDate('date_from', '>=', Carbon::today())
            ->get(['date_from', 'date_end', 'status']);

        $trips = [];

        foreach ($bookings as $booking) {
            $trips[] = [
                'start'  => Carbon::parse($booking->date_from)->toDateString(),
                'end'    => Carbon::parse($booking->date_end)->toDateString(),
                'status' => $booking->status,
            ];
        }

        return response()->json([
            'trips' => $trips
        ]);
    }


    public function get_all_filter(Request $request)
    {
        $user = auth()->user();

        // بناء استعلام الحجوزات حسب الفلاتر
        $query = Order_Booking::where('user_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('date_from', '>=', Carbon::parse($request->date_from));
        }

        if ($request->has('date_end')) {
            $query->whereDate('date_end', '<=', Carbon::parse($request->date_end));
        }

        if ($request->has('car_id')) {
            $query->where('car_id', $request->car_id);
        }

        // تنفيذ الاستعلام وجلب النتائج
        $bookings = $query->with([
            'car_details',
            'car_details.car_image',
        ])->orderBy('date_from', 'desc')->get();

        // حساب الإحصائيات بدون التأثر بالفلاتر مثل status
        $baseStatsQuery = Order_Booking::where('user_id', $user->id);

        if ($request->has('date_from')) {
            $baseStatsQuery->whereDate('date_from', '>=', Carbon::parse($request->date_from));
        }

        if ($request->has('date_end')) {
            $baseStatsQuery->whereDate('date_end', '<=', Carbon::parse($request->date_end));
        }

        if ($request->has('car_id')) {
            $baseStatsQuery->where('car_id', $request->car_id);
        }

        // حساب meta
        $meta = [
            'total' => (clone $baseStatsQuery)->count(),

            'pending' => (clone $baseStatsQuery)->where('status', 'pending')->count(),

            'Confiremed' => (clone $baseStatsQuery)->where('status', 'Confiremed')->count(),
            'picked_up' => (clone $baseStatsQuery)->where('status', 'picked_up')->count(),
            'Returned' => (clone $baseStatsQuery)->where('status', 'Returned')->count(),
            'Completed' => (clone $baseStatsQuery)->where('status', 'Completed')->count(),
            'Canceled' => (clone $baseStatsQuery)->where('status', 'Canceled')->count(),
        ];

        return response()->json([
            'status' => true,
            'data' => $bookings,
            'meta' => $meta,
        ]);
    }




public function get_all_filter_admin(Request $request)
{
    // Start with a query builder instead of getting all results
    $query = Order_Booking::query();

    $user = Auth::user(); // الحصول على بيانات المستخدم الحالي

    // إذا كان المستخدم من النوع 2 (ممثل)، نضيف شرط has_representative == 0
    if ($user && $user->type == 2) {
        $query->where('has_representative', 0);
    }

    if ($request->has('status')) {
        $query->where('status', $request->status);
    }

    if ($request->has('date_from')) {
        $query->whereDate('date_from', '>=', Carbon::parse($request->date_from));
    }

    if ($request->has('date_end')) {
        $query->whereDate('date_end', '<=', Carbon::parse($request->date_end));
    }

    if ($request->has('car_id')) {
        $query->where('car_id', $request->car_id);
    }

    // Execute the query with eager loading
    $bookings = $query->with([
        'car_details',
        'car_details.car_image',
    ])->orderBy('date_from', 'desc')->get();

    // For base stats, also start with a query builder
    $baseStatsQuery = Order_Booking::query();

    if ($request->has('date_from')) {
        $baseStatsQuery->whereDate('date_from', '>=', Carbon::parse($request->date_from));
    }

    if ($request->has('date_end')) {
        $baseStatsQuery->whereDate('date_end', '<=', Carbon::parse($request->date_end));
    }

    if ($request->has('car_id')) {
        $baseStatsQuery->where('car_id', $request->car_id);
    }

    // Calculate meta
    $meta = [
        'total' => $baseStatsQuery->count(),
        'pending' => (clone $baseStatsQuery)->where('status', 'pending')->count(),
        'Confiremed' => (clone $baseStatsQuery)->where('status', 'Confiremed')->count(),
        'picked_up' => (clone $baseStatsQuery)->where('status', 'picked_up')->count(),
        'Returned' => (clone $baseStatsQuery)->where('status', 'Returned')->count(),
        'Completed' => (clone $baseStatsQuery)->where('status', 'Completed')->count(),
        'Canceled' => (clone $baseStatsQuery)->where('status', 'Canceled')->count(),
    ];

    return response()->json([
        'status' => true,
        'data' => $bookings,
        'meta' => $meta,
    ]);
}


    public function change_status_admin(Request $request, $id)
    {
        $user = auth()->user();
        $validate = Validator::make($request->all(), [
            'status' => 'required|in:pending,Confiremed,picked_up,Returned,Completed,Canceled'
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 400);
        }

        $booking = Order_Booking::find($id);

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود أو غير تابع لك',
            ], 404);
        }

        $newStatus = $request->status;
        $currentStatus = $booking->status;

        // الحالات الخاصة وتحقق الشروط
        switch ($newStatus) {
            case 'Confiremed':
                if ($currentStatus !== 'pending') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى Confiremed إلا إذا كانت الحالة السابقة pending',
                    ], 400);
                }
                $booking->status = 'Confiremed';
                break;

            case 'picked_up':
                if ($currentStatus !== 'Confiremed' || !$booking->is_paid) {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى picked_up إلا إذا كانت الحالة السابقة Confiremed وتم الدفع',
                    ], 400);
                }
                $booking->status = 'picked_up';


                break;

            case 'Returned':
                if ($currentStatus !== 'picked_up') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى Returned إلا إذا كانت الحالة السابقة picked_up',
                    ], 400);
                }
                $booking->status = 'Returned';
                break;

            case 'Completed':
                // if (!in_array($currentStatus, ['picked_up', 'Returned'])) {
                if ($currentStatus !== 'Returned') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن إنهاء الحجز إلا إذا كانت الحالة Returned',
                    ], 400);
                }
                $booking->status = 'Completed';
                $Review = Review::create([
                    'user_id' => $user->id,
                    'car_id' => $booking->car_id,
                    'status' => 'pending',

                ]);
                break;

            case 'Canceled':
                $booking->status = 'Canceled';
                break;

            default:
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير مدعومة',
                ], 400);
        }

        $booking->save();

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الحجز بنجاح',
            'new_status' => $booking->status,
        ]);
    }


    public function change_status_renter(Request $request, $id)
    {

        $user = auth()->user();
        $validate = Validator::make($request->all(), [
            'status' => 'required|in:picked_up,Returned,Canceled'
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 400);
        }

        $booking = Order_Booking::find($id);

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود أو غير تابع لك',
            ], 404);
        }

        $newStatus = $request->status;
        $currentStatus = $booking->status;

        // الحالات الخاصة وتحقق الشروط
        switch ($newStatus) {


            case 'picked_up':
                if ($currentStatus !== 'Confiremed' || !$booking->is_paid) {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى picked_up إلا إذا كانت الحالة السابقة Confiremed وتم الدفع',
                    ], 400);
                }
                $booking->status = 'picked_up';


                break;

            case 'Returned':
                if ($currentStatus !== 'picked_up') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن تغيير الحالة إلى Returned إلا إذا كانت الحالة السابقة picked_up',
                    ], 400);
                }
                $booking->status = 'Returned';
                break;

            case 'Canceled':
                $booking->status = 'Canceled';
                break;

            default:
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير مدعومة',
                ], 400);
        }

        $booking->save();

        event(new \App\Events\PrivateNotificationEvent($booking, 'success', $user->id));


        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الحجز بنجاح',
            'new_status' => $booking->status,
        ]);
    }


    public function change_status_owner(Request $request, $id)
    {

        $user = auth()->user();


        $validate = Validator::make($request->all(), [
            'status' => 'required|in:Completed,Canceled'
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 400);
        }

        $booking = Order_Booking::find($id);


        $car = Cars::find($booking->car_id);

        if ($car->owner_id != $user->id) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود.او غير تابع لك',
            ], 404);
        }

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'الحجز غير موجود أو غير تابع لك',
            ], 404);
        }

        $newStatus = $request->status;
        $currentStatus = $booking->status;

        // الحالات الخاصة وتحقق الشروط
        switch ($newStatus) {



            case 'Completed':
                // if (!in_array($currentStatus, ['picked_up', 'Returned'])) {
                if ($currentStatus !== 'Returned') {
                    return response()->json([
                        'status' => false,
                        'message' => 'لا يمكن إنهاء الحجز إلا إذا كانت الحالة Returned',
                    ], 400);
                }
                $booking->status = 'Completed';
                $Review = Review::create([
                    'user_id' => $user->id,
                    'car_id' => $booking->car_id,
                    'status' => 'pending',

                ]);
                break;

            case 'Canceled':
                $booking->status = 'Canceled';
                break;

            default:
                return response()->json([
                    'status' => false,
                    'message' => 'الحالة غير مدعومة',
                ], 400);
        }

        $booking->save();
        event(new \App\Events\PrivateNotificationEvent($booking, 'success', $user->id));
        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الحجز بنجاح',
            'new_status' => $booking->status,
        ]);
    }


    public function change_is_paid($id)
    {


        $booking = Order_Booking::find($id);

        $booking->is_paid = 1;

        $booking->save();

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث حالة الحجز بنجاح',
            'new_status' => $booking->is_paid,
        ]);
    }





    public function accept_order($order_booking_id)
    {
        // الحصول على طلب الحجز
        $order = Order_Booking::find($order_booking_id);

        // التحقق من وجود الطلب
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // التحقق من أن has_representative == 0
        if ($order->has_representative != 0) {
            return response()->json([
                'status' => false,
                'message' => 'This order already has a representative'
            ], 400);
        }

        // الحصول على الممثل الحالي (المسؤول عن الطلب)
        $representative_id = Auth::user()->representative; // أو أي طريقة أخرى تحصل بها على representative_id

        try {
            // إنشاء سجل جديد في Represen_Order
            $represenOrder = Represen_Order::create([
                'order__booking_id' => $order_booking_id,
                'representative_id' => $representative_id->id,
                'status' => 'pending'
            ]);

            // تحديث حالة الطلب إذا لزم الأمر (اختياري)
            $order->update([
                'has_representative' => 1,
                'status' => 'Confiremed' // أو أي حالة أخرى تريدها
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Order accepted successfully',
                'data' => $represenOrder
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to accept order: ' . $e->getMessage()
            ], 500);
        }
    }
}
