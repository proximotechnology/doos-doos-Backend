<?php

namespace App\Http\Controllers;

use App\Models\Order_Booking;
use App\Models\Represen_Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RepresenOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function my_order()
    {
        // الحصول على id الممثل الحالي
        $representative_id = Auth::user()->representative; // أو Auth::user()->id إذا كان Representative يستخدم جدول users

        // جلب جميع طلبات الممثل مع تفاصيل كل طلب
        $orders = Represen_Order::with([
                'order_booking',
                'order_booking.car_details',
                'order_booking.car_details.car_image',
                'order_booking.user' // إذا كنت تريد بيانات العميل
            ])
            ->where('representative_id', $representative_id->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $orders
        ]);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function show($id)
    {
        // الحصول على الممثل الحالي
        $representative = Auth::user()->representative;

        // البحث عن الطلب مع التحقق أنه يخص هذا الممثل
        $order = Represen_Order::with([
                'order_booking',
                'order_booking.car_details',
                'order_booking.car_details.car_image',
                'order_booking.user',
                'representative'
            ])
            ->where('id', $id)
            ->where('representative_id', $representative->id)
            ->first();

        // إذا لم يتم العثور على الطلب
        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found or you do not have permission to view this order'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $order
        ]);
    }


    public function update_status(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:on_way,arrived' // Only allow these two status values
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'حدث خطأ أثناء التسجيل: ' . $validator->errors(),
                'status' => false
            ], 422);
        }

        $representative = Auth::user()->representative;
        $status = $request->status;

        $represenOrder = Represen_Order::where('id', $id)
            ->where('representative_id', $representative->id)
            ->first();

        if (!$represenOrder) {
            return response()->json([
                'status' => false,
                'message' => 'لم يتم العثور على الطلب'
            ], 404);
        }

        $orderBooking = $represenOrder->order_booking;

        // Validate status transitions
        if ($status == 'on_way') {
            // For 'on_way', order must be 'confirmed'
            if ($orderBooking->status !== 'confirmed') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن تحديث الحالة إلى "on_way" إلا إذا كانت حالة الحجز "confirmed"'
                ], 422);
            }
            if ($represenOrder->status !== 'pending') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن تحديث الحالة إلى "on_way" إلا إذا كانت حالة الحجز "pending"'
                ], 422);
            }
        } elseif ($status == 'arrived') {
            // For 'arrived', represenOrder must be 'on_way' first
            if ($represenOrder->status !== 'on_way') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن تحديث الحالة إلى "arrived" إلا إذا كانت الحالة الحالية "on_way"'
                ], 422);
            }
        }

        // Update both records
        $represenOrder->update(['status' => $status]);
        $orderBooking->update(['repres_status' => $status]);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الحالة بنجاح'
        ], 200);
    }



    public function track_user_order($order_booking_id)
    {
        // الحصول على المستخدم الحالي
        $user = Auth::user();

        // البحث عن طلب الحجز الخاص بالمستخدم
        $orderBooking = Order_Booking::where('id', $order_booking_id)
                            ->where('user_id', $user->id)
                            ->first();

        // التحقق من وجود الطلب
        if (!$orderBooking) {
            return response()->json([
                'status' => false,
                'message' => 'لم يتم العثور على طلب الحجز أو ليس لديك صلاحية الوصول إليه'
            ], 404);
        }

        // التحقق من أن حالة الطلب هي confirmed
        if ($orderBooking->status != 'confirmed') {
            return response()->json([
                'status' => false,
                'message' => 'حالة طلب الحجز غير مؤكدة بعد'
            ], 422);
        }

        // التحقق من وجود مندوب معين لهذا الطلب
        $represenOrder = $orderBooking->represen_order()->first();

        if (!$represenOrder) {
            return response()->json([
                'status' => false,
                'message' => 'لم يتم تعيين مندوب لهذا الطلب بعد'
            ], 404);
        }

        // جلب معلومات المندوب والمستخدم الخاص به
        $representative = $represenOrder->representative;
        $representativeUser = $representative->user;

        // إعداد البيانات للإرجاع
        $data = [
            'booking_status' => $orderBooking->status,
            'order_repres_status' => $orderBooking->repres_status,
            'order_repres_id' => $represenOrder->id,

            'representative' => [
                'id' => $representative->id,
                'status' => $representative->status,
                'user_info' => [
                    'id' => $representativeUser->id,
                    'name' => $representativeUser->name,
                    'phone' => $representativeUser->phone,
                    'email' => $representativeUser->email
                ]
            ]
        ];

        return response()->json([
            'status' => true,
            'message' => 'تم العثور على بيانات المندوب',
            'data' => $data
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */

    public function show_order($represen_order_id)
    {
        try {
            // الحصول على المستخدم الحالي
            $user = Auth::user();

            // جلب علاقة المندوب مع الطلب والعلاقات الأخرى
            $represenOrder = Represen_Order::with([
                'order_booking.car',
                'order_booking.station',
                'representative.user'
            ])
            ->where('id', $represen_order_id)
            ->first();

            // التحقق من وجود العلاقة
            if (!$represenOrder) {
                return response()->json([
                    'status' => false,
                    'message' => 'سجل تعيين المندوب غير موجود'
                ], 404);
            }

            // التحقق من أن الطلب يعود لنفس المستخدم
            if ($represenOrder->order_booking->user_id != $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'ليس لديك صلاحية الوصول إلى هذا الطلب'
                ], 403);
            }

            $orderBooking = $represenOrder->order_booking;

            // إعداد بيانات الطلب الأساسية
            $orderData = [
                'represen_order_id' => $represenOrder->id, // إضافة ID العلاقة
                'orderBooking_id' => $orderBooking->id,
                'car' => [
                    'id' => $orderBooking->car->id,
                    'make' => $orderBooking->car->make,
                    'model' => $orderBooking->car->model->name,
                ],
                'date_from' => $orderBooking->date_from,
                'date_end' => $orderBooking->date_end,
                'total_price' => $orderBooking->total_price,
                'status' => $orderBooking->status,
                'repres_status' => $orderBooking->repres_status,
                'station' => $orderBooking->station ? [
                    'id' => $orderBooking->station->id,
                    'name' => $orderBooking->station->name,
                    'lat' => $orderBooking->station->lat,
                    'lang' => $orderBooking->station->lang,
                ] : null,
                'lat' => $orderBooking->lat ?: null,
                'lang' => $orderBooking->lang ?: null,

                'with_driver' => $orderBooking->with_driver,
                'driver_type' => $orderBooking->driver_type,
                'representative' => [
                    'id' => $represenOrder->representative->id,
                    'status' => $represenOrder->representative->status,
                    'order_status' => $represenOrder->status, // حالة المندوب في هذا الطلب
                    'user_info' => [
                        'id' => $represenOrder->representative->user->id,
                        'name' => $represenOrder->representative->user->name,
                        'phone' => $represenOrder->representative->user->phone,
                        'profile_image' => $represenOrder->representative->user->profile_image_url
                    ]
                ]
            ];

            return response()->json([
                'status' => true,
                'message' => 'تم جلب بيانات الطلب بنجاح',
                'data' => $orderData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب بيانات الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function user_update_to_pickup($order_repres_id)
    {
        try {
            // الحصول على المستخدم الحالي
            $user = Auth::user();

            // جلب علاقة المندوب مع الطلب والعلاقات الأخرى
            $represenOrder = Represen_Order::with(['order_booking', 'representative'])
                ->where('id', $order_repres_id)
                ->first();

            // التحقق من وجود العلاقة
            if (!$represenOrder) {
                return response()->json([
                    'status' => false,
                    'message' => 'سجل تعيين المندوب غير موجود'
                ], 404);
            }

            // التحقق من أن الطلب يعود لنفس المستخدم
            if ($represenOrder->order_booking->user_id != $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'ليس لديك صلاحية الوصول إلى هذا الطلب'
                ], 403);
            }

            $orderBooking = $represenOrder->order_booking;

            // التحقق من شروط التحديث
            if ($orderBooking->status != 'confirmed') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن التحديث إلا إذا كانت حالة الحجز confirmed'
                ], 422);
            }

            if ($represenOrder->status != 'arrived') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن التحديث إلا إذا كانت حالة المندوب arrived'
                ], 422);
            }

            // بدء المعاملة للتأكد من سلامة التحديثات
            DB::beginTransaction();

            try {
                // تحديث حالة الطلب
                $orderBooking->update([
                    'repres_status' => 'pick_up',
                    'status' => 'pick_up' // أو أي حالة تريدينها بعد pick_up
                ]);

                // تحديث حالة المندوب في هذا الطلب
                $represenOrder->update([
                    'status' => 'pick_up'
                ]);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'تم تحديث الحالة إلى pick_up بنجاح',
                    'data' => [
                        'order_status' => 'pick_up',
                        'repres_status' => 'pick_up'
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'فشل في تحديث الحالة',
                    'error' => $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء معالجة الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function user_update_to_return($order_repres_id)
    {
        try {
            // الحصول على المستخدم الحالي
            $user = Auth::user();

            // جلب علاقة المندوب مع الطلب والعلاقات الأخرى
            $represenOrder = Represen_Order::with(['order_booking', 'representative'])
                ->where('id', $order_repres_id)
                ->first();

            // التحقق من وجود العلاقة
            if (!$represenOrder) {
                return response()->json([
                    'status' => false,
                    'message' => 'سجل تعيين المندوب غير موجود'
                ], 404);
            }

            // التحقق من أن الطلب يعود لنفس المستخدم
            if ($represenOrder->order_booking->user_id != $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'ليس لديك صلاحية الوصول إلى هذا الطلب'
                ], 403);
            }

            $orderBooking = $represenOrder->order_booking;

            // التحقق من شروط التحديث
            if ($orderBooking->status != 'pick_up') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن التحديث إلا إذا كانت حالة الحجز pick_up'
                ], 422);
            }

            if ($represenOrder->status != 'pick_up') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن التحديث إلا إذا كانت حالة المندوب pick_up'
                ], 422);
            }

            // بدء المعاملة للتأكد من سلامة التحديثات
            DB::beginTransaction();

            try {
                // تحديث حالة الطلب
                $orderBooking->update([
                    'repres_status' => 'returned',
                    'status' => 'returned' // أو أي حالة تريدينها بعد pick_up
                ]);

                // تحديث حالة المندوب في هذا الطلب
                $represenOrder->update([
                    'status' => 'returned'
                ]);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'تم تحديث الحالة إلى returned بنجاح',
                    'data' => [
                        'order_status' => 'returned',
                        'repres_status' => 'returned'
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'فشل في تحديث الحالة',
                    'error' => $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء معالجة الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function owner_update_status($order_repres_id)
    {
        try {
            // الحصول على المستخدم الحالي
            $user = Auth::user();

            // جلب علاقة المندوب مع الطلب والعلاقات الأخرى
            $represenOrder = Represen_Order::with(['order_booking', 'representative'])
                ->where('id', $order_repres_id)
                ->first();

            // التحقق من وجود العلاقة
            if (!$represenOrder) {
                return response()->json([
                    'status' => false,
                    'message' => 'سجل تعيين المندوب غير موجود'
                ], 404);
            }

            // التحقق من أن الطلب يعود لنفس المستخدم
            if ($represenOrder->order_booking->user_id != $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'ليس لديك صلاحية الوصول إلى هذا الطلب'
                ], 403);
            }

            $orderBooking = $represenOrder->order_booking;

            // التحقق من شروط التحديث
            if ($orderBooking->status != 'pick_up') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن التحديث إلا إذا كانت حالة الحجز pick_up'
                ], 422);
            }

            if ($represenOrder->status != 'pick_up') {
                return response()->json([
                    'status' => false,
                    'message' => 'لا يمكن التحديث إلا إذا كانت حالة المندوب pick_up'
                ], 422);
            }

            // بدء المعاملة للتأكد من سلامة التحديثات
            DB::beginTransaction();

            try {
                // تحديث حالة الطلب
                $orderBooking->update([
                    'repres_status' => 'returned',
                    'status' => 'returned' // أو أي حالة تريدينها بعد pick_up
                ]);

                // تحديث حالة المندوب في هذا الطلب
                $represenOrder->update([
                    'status' => 'returned'
                ]);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'تم تحديث الحالة إلى returned بنجاح',
                    'data' => [
                        'order_status' => 'returned',
                        'repres_status' => 'returned'
                    ]
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'فشل في تحديث الحالة',
                    'error' => $e->getMessage()
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء معالجة الطلب',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
