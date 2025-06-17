<?php

namespace App\Http\Controllers\Registration;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\registartion\RegisterUser; // Ensure the namespace is correct
use App\Services\registartion\register; // Ensure the namespace is correct
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache; // Import Cache facade
use Illuminate\Support\Str;
use Twilio\Rest\Client;
use App\Helpers\OtpHelper;

class RegisterController extends Controller
{
    protected $userService;

    public function __construct(register $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Handle the registration of a new user.
     *
     * @param RegisterUser   $request
     * @return JsonResponse
     */
    public function register(RegisterUser $request): JsonResponse
    {
        $validatedData = $request->validated();

        // Pass the modified request data to the service
        $user = $this->userService->register($validatedData);

        /*  if (isset($validatedData['email'])) {
            OtpHelper::sendOtpEmail($user->id);
        }/*elseif(isset($validatedData['phone']))
        {
            $this->sendOtp_mobile($user->id);
        }*/

        return response()->json([
            'message' => 'User  registered successfully',
            'user' => $user,
        ], 201);
    }





    private function sendOtp_mobile($id)
    {
        $user = User::findOrFail($id);
        $otp = Str::random(6);

        // Store OTP in cache with ID and phone number, set expiration time (e.g., 5 minutes)
        Cache::put('otp_' . $user->id, ['otp' => $otp, 'phone' => $user->phone], 300);

        // Send OTP via SMS
        $this->sendSms($user->phone, $otp);

        return $otp;
    }


    public function verfication_otp(Request $request)
    {
        // Validate the request
        $request->validate([
            'otp' => 'required|string|size:6', // Ensure OTP is a string of size 6
        ]);

        // Get the authenticated user
        $user = Auth::user();

        // Check if the user is authenticated
        if (!$user) {
            return response()->json(['message' => 'User  not authenticated.'], 401);
        }

        try {
            // Call the verifyOtp method from the service
            $this->userService->verifyOtp($request->input('otp'), $user);

            return response()->json(['message' => 'OTP verified successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }


    private function sendSms($phone, $otp)
    {
        $account_sid = env('TWILIO_SID');
        $auth_token = env('TWILIO_TOKEN');
        $twilio_number = env('TWILIO_FROM');

        $client = new Client($account_sid, $auth_token);
        $client->messages->create($phone, [
            'from' => $twilio_number,
            'body' => "Your OTP code is: $otp"
        ]);
    }
}
