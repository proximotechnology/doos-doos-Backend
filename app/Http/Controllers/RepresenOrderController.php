<?php

namespace App\Http\Controllers;

use App\Models\Represen_Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

    public function update_status($id)
    {
        // الحصول على الممثل الحالي
        $representative = Auth::user()->representative;


    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Represen_Order $represen_Order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Represen_Order $represen_Order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Represen_Order $represen_Order)
    {
        //
    }
}
