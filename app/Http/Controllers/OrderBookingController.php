<?php

namespace App\Http\Controllers;

use App\Models\Driver_Price;
use App\Models\Review;
use App\Models\Driver_license;
use App\Models\User;
use App\Models\Order_Booking;
use App\Models\Cars;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;


use Illuminate\Support\Facades\DB;



class OrderBookingController extends Controller
{





    public function store(Request $request, $id)
    {
        $user = auth()->user();
        $userlogged = User::find($user->id);
        $request['user_id'] = $user->id;
        $request['car_id'] = $id;

        $validate = Validator::make($request->all(), [
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
        ]);

        if ($validate->fails()) {
            return response()->json(['error' => $validate->errors()], 400);
        }

        $car = Cars::find($request->car_id);
        if (!$car || $car->status !== 'active' || $car->is_rented == 1) {
            return response()->json([
                'status' => false,
                'message' => 'السيارة غير متاحة.',
            ], 404);
        }

        $dateFrom = Carbon::parse($request->date_from);
        $dateEnd = Carbon::parse($request->date_end);
        $days = $dateFrom->diffInDays($dateEnd);

        if ($days < $car->min_day_trip) {
            return response()->json([
                'status' => false,
                'message' => 'مدة الحجز أقل من الحد الأدنى المسموح به: ' . $car->min_day_trip . ' أيام.',
            ], 422);
        }

        if (!is_null($car->max_day_trip) && $days > $car->max_day_trip) {
            return response()->json([
                'status' => false,
                'message' => 'مدة الحجز تتجاوز الحد الأقصى المسموح به: ' . $car->max_day_trip . ' أيام.',
            ], 422);
        }

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
                'message' => 'السيارة محجوزة في التاريخ المختار.',
            ], 422);
        }

        $total_price = $car->price * $days;

        if ($request->with_driver == true) {
            $driver_price = Driver_Price::find(1);
            if (!$driver_price) {
                return response()->json([
                    'status' => false,
                    'message' => 'يرجى تحديد سعر السائق',
                ], 422);
            }

            $total_price += $driver_price->price;
        }

        DB::beginTransaction(); // بدء المعاملة

        try {
            $booking = Order_Booking::create([
                'user_id' => $request->user_id,
                'car_id' => $request->car_id,
                'date_from' => $request->date_from,
                'date_end' => $request->date_end,
                'with_driver' => $request->with_driver,
                'total_price' => $total_price,
            ]);

            if ($user->has_license == 0) {
                $existing_license = Driver_license::where('user_id', $user->id)->first();

                if ($existing_license && $existing_license->number == $request->number) {
                    DB::rollBack();
                    return response()->json([
                        'status' => false,
                        'message' => 'رقم الرخصة مكرر.',
                    ], 422);
                }

                if ($request->hasFile('image_license')) {
                    $image = $request->file('image_license');
                    $path = $image->store('car_images', 'public');
                }

                Driver_license::create([
                    'user_id' => $user->id,
                    'country' => $request->country,
                    'state' => $request->state,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'birth_date' => $request->birth_date,
                    'expiration_date' => $request->expiration_date,
                    'image' => $request->image ?? null, // أو استخدم $path إذا رفعت الصورة
                    'number' => $request->number
                ]);

                $userlogged->has_license = 1;
                $userlogged->save();
            }

            DB::commit(); // تأكيد المعاملة




            return response()->json([
                'status' => true,
                'message' => 'تم حجز السيارة بنجاح.',
                'booking' => $booking,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack(); // التراجع عن كل العمليات

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء تنفيذ العملية.',
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

        // بناء استعلام الحجوزات حسب الفلاتر
        $query = Order_Booking::all();

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
        $baseStatsQuery = Order_Booking::all();

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
}
