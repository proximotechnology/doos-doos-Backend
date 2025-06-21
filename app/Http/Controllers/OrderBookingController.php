<?php

namespace App\Http\Controllers;

use App\Models\Driver_license;
use App\Models\User;
use App\Models\Order_Booking;
use App\Models\Cars;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Carbon\Carbon;


class OrderBookingController extends Controller
{

    public function index()
    {
        //
    }


    public function store(Request $request)
    {

        $user = auth()->user();

        $userlogged = User::find($user->id);

        $request['user_id'] = $user->id;

        $validate = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'car_id' => 'required|exists:cars,id',
            'date_from' => 'required|date|after_or_equal:today',
            'date_end' => 'required|date|after:date_from',
            // 'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'country' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'birth_date' => 'nullable|string|max:255',
            'expiration_date' => 'nullable|date|after_or_equal:today',
            'number' => 'nullable|numeric',
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

        // حساب مدة الحجز بالأيام
        $dateFrom = Carbon::parse($request->date_from);
        $dateEnd = Carbon::parse($request->date_end);
        $days = $dateFrom->diffInDays($dateEnd);

        // تحقق من المدة بالنسبة للـ min_day_trip و max_day_trip
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


        // التحقق من التداخل في المواعيد مع حجوزات سابقة
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

        $booking = Order_Booking::create([
            'user_id' => $request->user_id,
            'car_id' => $request->car_id,
            'date_from' => $request->date_from,
            'date_end' => $request->date_end,
            'total_price' => $total_price,
        ]);



        if ($user->has_license == 0) {


            $Driver_license = Driver_license::where('user_id', $user->id)->first();

            if ($Driver_license->number == $request->number) {
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
                // 'image' => $path ?? null,
                'image' => $request->image,
                'number' => $request->number
            ]);

            $userlogged->has_license = 1;
            $userlogged->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'تم حجز السيارة بنجاح.',
            'booking' => $booking,
        ], 200);
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
            'approved' => (clone $baseStatsQuery)->where('status', 'approved')->count(),
            'rejected' => (clone $baseStatsQuery)->where('status', 'rejected')->count(),
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
        $booking = Order_Booking::with(['car_details', 'car_details.car_image'])->find($id);

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
        $booking = Order_Booking::with(['car_details', 'car_details.car_image', 'user'])->find($id);

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

    public function update(Request $request, Order_Booking $order_Booking)
    {
        //
    }


    public function destroy(Order_Booking $order_Booking)
    {
        //
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
}
