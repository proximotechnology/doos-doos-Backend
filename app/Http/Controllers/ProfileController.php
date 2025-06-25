<?php

namespace App\Http\Controllers;

use App\Models\profile;
use Illuminate\Http\Request;

use App\Events\public_notifiacation;
use App\Events\PrivateNotificationEvent;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
//call PrivateChannel
use Illuminate\Broadcasting\PrivateChannel;


use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $profile = profile::where('user_id', $user->id)->first();


        $type = 'subscripe';
        $message = 'subscripe';
        event(new public_notifiacation('subscripe', 'subscripe'));

        // event(new PrivateNotificationEvent('تمت عملية الدفع بنجاح', 'success', $user->id));



        Log::info('Event fired');
        return response()->json($profile);
    }

    public function get_my_profile()
    {
        $user = auth()->user();
        $profile = profile::where('user_id', $user->id)->first();


        return response()->json($profile);
    }



    public function get_user_profile($id)
    {

        $profile = profile::where('user_id', $id)->first();
        return response()->json($profile);
    }

    public function store(Request $request)
    {

        $user = $request->user();
        $profile = profile::where('user_id', $user->id)->first();

        if ($profile) {
            return response()->json(['error' => 'Profile already exists for this user.'], 400);
        }

        $request['user_id'] = $user->id;

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'user_id' => 'required|integer|exists:users,id',
            'address_1' => 'required|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'zip_code' => 'required|string|max:20',
            'city' => 'required|string|max:255',
            'image' => 'nullable|image|max:2048', // صورة بحد أقصى 2MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('profile_images', 'public');
        }

        $profile = Profile::create($data);
        return response()->json($profile, 201);
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        $profile = profile::where('user_id', $user->id)->first();
        //    dd(        $request->all());
        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'user_id' => 'sometimes|integer|exists:users,id',
            'address_1' => 'sometimes|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'zip_code' => 'sometimes|string|max:20',
            'city' => 'sometimes|string|max:255',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('profile_images', 'public');
        }

        $profile->update($data);
        return response()->json([
            'message' => 'Profile updated successfully',
            $profile
        ]);
    }

    public function destroy(Profile $profile)
    {
        $profile->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
