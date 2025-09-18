<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OTPMail;
use App\Mail\SubscriberMail;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class SubscriberController extends Controller
{
    //
    public function index()
    {
        $user = auth('sanctum')->user();
        if ($user->type == 1 && ! $user->can('Read-Subscribers')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission',
            ], 403);
        }

        $subscribers = Subscriber::all();
        return response()->json([
            'status' => true,
            'data' => $subscribers
        ], 200);
    }



    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|unique:subscribers,email',
        ]);

        $subscriber = Subscriber::create($data);

        Mail::to($request->email)->send(new SubscriberMail($request->email, 'test'));

        return response()->json([
            'message' => 'Subscription successful 🎉',
            'data' => $subscriber
        ], Response::HTTP_CREATED);
    }



    public function destroy(Request $request, Subscriber $subscriber)
    {
        $user = auth('sanctum')->user();

        // التحقق من صلاحية الحذف
        if ($user->type == 1 && ! $user->can('Delete-Subscribers')) {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to delete subscribers',
            ], 403);
        }

        try {
            $subscriber->delete();

            return response()->json([
                'status' => true,
                'message' => 'Subscriber deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting subscriber: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء حذف المشترك',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }
}
