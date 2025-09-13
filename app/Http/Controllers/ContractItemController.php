<?php

namespace App\Http\Controllers;

use App\Models\ContractItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContractItemController extends Controller
{
// استعراض جميع العناصر
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 2); // افتراضي 15 عنصر في الصفحة
            $contractItems = ContractItem::paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $contractItems
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'حدث خطأ أثناء جلب العناصر',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }
    // تخزين عنصر جديد
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item' => 'required|string' // تم إزالة max:255 للسماح بنصوص طويلة
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $contractItem = ContractItem::create($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $contractItem
        ], 201);
    }

    // استعراض عنصر محدد
    public function show($id)
    {
        $contractItem = ContractItem::find($id);

        if (!$contractItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'العنصر غير موجود'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $contractItem
        ], 200);
    }

    // تعديل عنصر
    public function update(Request $request, $id)
    {
        $contractItem = ContractItem::find($id);

        if (!$contractItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'العنصر غير موجود'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'item' => 'required|string' // تم إزالة max:255 للسماح بنصوص طويلة
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $contractItem->update($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $contractItem
        ], 200);
    }

    // حذف عنصر
    public function destroy($id)
    {
        $contractItem = ContractItem::find($id);

        if (!$contractItem) {
            return response()->json([
                'status' => 'error',
                'message' => 'العنصر غير موجود'
            ], 404);
        }

        $contractItem->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'تم حذف العنصر بنجاح'
        ], 200);
    }
}

