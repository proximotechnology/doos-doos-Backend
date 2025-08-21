<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Order_Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContractController extends Controller
{
    public function userContracts(Request $request)
    {
        $user = Auth::user();

        // البدء بعقود المستخدم
        $contracts = Contract::with(['booking.car.owner', 'booking.user'])
            ->whereHas('booking', function($query) use ($user) {
                $query->where('user_id', $user->id);
            });

        // تطبيق الفلاتر إذا وجدت
        if ($request->has('order_booking_id')) {
            $orderId = $request->order_booking_id;

            // التحقق من أن order_booking_id خاص بالمستخدم الحالي
            $isValidOrder = Order_Booking::where('id', $orderId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$isValidOrder) {
                return response()->json([
                    'status' => false,
                    'message' => 'معرف الحجز غير صالح أو لا ينتمي لك'
                ], 403);
            }

            $contracts->where('order_booking_id', $orderId);
        }

        if ($request->has('status')) {
            $contracts->where('status', $request->status);
        }

        $contracts = $contracts->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $this->formatContractsData($contracts)
        ]);
    }

    /**
     * الحصول على عقود صاحب السيارة
     */
public function ownerContracts(Request $request)
{
    $user = Auth::user();
    
    // بناء الاستعلام الأساسي
    $contracts = Contract::with([
            'booking.car.owner', 
            'booking.user',
            'booking.car.brand',
            'booking.car.model'
        ])
        ->whereHas('booking.car', function($query) use ($user) {
            $query->where('owner_id', $user->id);
        });

    // تصفية حسب order_booking_id
    if ($request->has('order_booking_id')) {
        $orderId = $request->order_booking_id;
        
        $isValidOrder = Order_Booking::where('id', $orderId)
            ->whereHas('car', function($query) use ($user) {
                $query->where('owner_id', $user->id);
            })
            ->exists();

        if (!$isValidOrder) {
            return response()->json([
                'status' => false,
                'message' => 'معرف الحجز غير صالح أو لا ينتمي لك'
            ], 403);
        }

        $contracts->where('order_booking_id', $orderId);
    }

    // تصفية حسب car_id
    if ($request->has('car_id')) {
        $carId = $request->car_id;
        
        $isValidCar = Cars::where('id', $carId)
            ->where('owner_id', $user->id)
            ->exists();

        if (!$isValidCar) {
            return response()->json([
                'status' => false,
                'message' => 'معرف السيارة غير صالح أو لا ينتمي لك'
            ], 403);
        }

        $contracts->whereHas('booking', function($query) use ($carId) {
            $query->where('car_id', $carId);
        });
    }

    // تصفية حسب status
    if ($request->has('status')) {
        $contracts->where('status', $request->status);
    }

    // تطبيق الترتيب والحصول على النتائج
    $contracts = $contracts->latest()->get();

    return response()->json([
        'status' => true,
        'data' => $this->formatContractsData($contracts)
    ]);
}


    /**
     * عرض عقد معين
     */
    public function show($id)
    {
        $contract = Contract::with(['booking.car.owner', 'booking.user'])->find($id);

        if (!$contract) {
            return response()->json([
                'status' => false,
                'message' => 'العقد غير موجود'
            ], 404);
        }

        $user = Auth::user();

        // التحقق من صلاحية المستخدم لرؤية العقد
        $isOwner = $contract->booking->car->owner_id == $user->id;
        $isUser = $contract->booking->user_id == $user->id;

        if (!$isOwner && !$isUser) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بمشاهدة هذا العقد'
            ], 403);
        }

        return response()->json([
            'status' => true,
            'data' => $this->formatContractData($contract),
            'user_type' => $isOwner ? 'owner' : 'user'
        ]);
    }

    /**
     * تنسيق بيانات العقد الواحد
     */
    protected function formatContractData($contract)
    {
        return [
            'id' => $contract->id,
            'order_booking_id' => $contract->order_booking_id,
            'status' => $contract->status,
            'otp_user' => $contract->otp_user,
            'otp_renter' => $contract->otp_renter,
            'created_at' => $contract->created_at,
            'updated_at' => $contract->updated_at,
            'booking' => [
                'id' => $contract->booking->id,
                'user_id' => $contract->booking->user_id,
                'car_id' => $contract->booking->car_id,
                'date_from' => $contract->booking->date_from,
                'date_end' => $contract->booking->date_end,
                'with_driver' => $contract->booking->with_driver,
                'payment_method' => $contract->booking->payment_method,
                'total_price' => $contract->booking->total_price,
                'status' => $contract->booking->status,
                'driver_type' => $contract->booking->driver_type,
                'created_at' => $contract->booking->created_at,
                'updated_at' => $contract->booking->updated_at,
                'car' => $contract->booking->car,
                'user' => $contract->booking->user,
            ]
        ];
    }

    /**
     * تنسيق بيانات العقود المتعددة
     */
    protected function formatContractsData($contracts)
    {
        return $contracts->map(function($contract) {
            return $this->formatContractData($contract);
        });
    }


    public function adminContracts(Request $request)
    {


        $contracts = Contract::with(['booking.car.owner', 'booking.user']);

        // تطبيق الفلاتر حسب order_booking_id
        if ($request->has('order_booking_id')) {
            $contracts->where('order_booking_id', $request->order_booking_id);
        }

        // تطبيق الفلاتر حسب car_id
        if ($request->has('car_id')) {
            $contracts->whereHas('booking', function($query) use ($request) {
                $query->where('car_id', $request->car_id);
            });
        }

        // تطبيق الفلاتر حسب status
        if ($request->has('status')) {
            $contracts->where('status', $request->status);
        }

        // تطبيق الفلاتر حسب user_id
        if ($request->has('user_id')) {
            $contracts->whereHas('booking', function($query) use ($request) {
                $query->where('user_id', $request->user_id);
            });
        }

        if ($request->has('owner_id')) {
            $contracts->whereHas('booking.car', function($query) use ($request) {
                $query->where('owner_id', $request->owner_id);
            });
        }

        $contracts = $contracts->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $this->formatContractsData($contracts)
        ]);
    }


    public function showadmin($id)
    {
        $contract = Contract::with(['booking.car.owner', 'booking.user'])->find($id);

        if (!$contract) {
            return response()->json([
                'status' => false,
                'message' => 'العقد غير موجود'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $this->formatContractData($contract),
        ]);
    }
}
