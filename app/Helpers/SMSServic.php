<?php

namespace App\Helpers;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class SMSService
{    public static function sendMessage(string $to, string $message)
    {
            $account_sid = env('TWILIO_SID');
        $auth_token = env('TWILIO_TOKEN');
        $twilio_number = env('TWILIO_FROM');

        $client = new Client($account_sid, $auth_token);
        $client->messages->create($to, [
            'from' => $twilio_number,
            'body' => "Your OTP  Contact code is: $message"
        ]);
    }
}
