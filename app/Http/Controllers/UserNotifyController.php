<?php

namespace App\Http\Controllers;

use App\Models\User_Notify;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use App\Events\public_notifiacation;


class UserNotifyController extends Controller
{

    public function index()
    {
        //
    }


    public function send_notify(Request $request)
    {

        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'Notify' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $notify = User_Notify::create([
            'notify' => $request->Notify,
            'is_read' => 'complete',
        ]);

        event(new public_notifiacation($notify, 'public'));

  



        return response()->json([
            'success' => true,
            'data' => $notify,
        ]);
    }



    public function my_notification()
    {
        $user = auth()->user();


        $my_notification = User_Notify::where(function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereNull('user_id');
        })->get();

        return response()->json($my_notification);
    }




    public function mark_read()
    {
        $user = auth()->user();

        $my_notifications = User_Notify::where('user_id', $user->id)->get();

        $my_notifications->each(function ($notification) {
            $notification->update(['is_read' => 'complete']);
        });

        return response()->json($my_notifications);
    }


    public function store(Request $request)
    {
        //
    }


    public function update(Request $request, User_Notify $user_Notify)
    {
        //
    }


    public function destroy(User_Notify $user_Notify)
    {
        //
    }
}
