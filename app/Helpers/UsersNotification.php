<?php

namespace App\Helpers;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use App\Models\Notification as DbNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class UsersNotification
{
    public static function sendNotificationUser($title, $body, $token, $userId)
    {
        try {
            $firebaseNotification = FirebaseNotification::create($title, $body);
            // $badgeCount = DbNotification::where('user_id', $userId)->where('user_type', User::class)->where('is_read', false)->count();
            $badgeCount = 0;
            $data = [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound'        => 'default',
            ];

            $androidConfig = AndroidConfig::fromArray([
                'priority'     => 'high',
                'notification' => [
                    'sound'         => 'default',
                    'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ]);

            $apnsConfig = ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound'    => 'default',
                        'category' => 'FLUTTER_NOTIFICATION_CLICK',
                        'badge'    => $badgeCount,
                    ],
                ],
            ]);

            // 4️⃣ إرسال الإشعار فقط إذا كان هناك token
            if ($token) {
                $factory = (new Factory)->withServiceAccount(storage_path(env('FIREBASE_CREDENTIALS_USER')));
                $messaging = $factory->createMessaging();

                $message = CloudMessage::withTarget('token', $token)
                    ->withNotification($firebaseNotification)
                    ->withData($data)
                    ->withAndroidConfig($androidConfig)
                    ->withApnsConfig($apnsConfig);

                try {
                    $messaging->send($message);
                    Log::info("✅ Notification sent to user ID: {$userId}");
                } catch (\Throwable $e) {
                    Log::error("❌ FCM send failed: " . $e->getMessage());
                }
            } else {
                Log::info("ℹ️ No FCM token found for user ID: {$userId}, skipping notification.");
            }

            return true;
        } catch (MessagingException $e) {
            Log::error("❌ Firebase Messaging Error: {$e->getMessage()}");
            return false;
        } catch (\Exception $e) {
            Log::error("❌ General Error: {$e->getMessage()}");
            return false;
        }
    }

    public static function sendToUser(User $user, $data)
    {
        if (!$user->fcm_token) {
            Log::warning("⚠️ Skipping notification: User {$user->id} has no FCM token.");
            return false;
        }
        return self::sendNotificationUser($data['title'], $data['body'], $user->fcm_token, $user->id);
    }
}
