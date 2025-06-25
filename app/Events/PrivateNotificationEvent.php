<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class PrivateNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $type;
    public $userId;

    public function __construct($message, $type, $userId)
    {
        $this->message = $message;
        $this->type = $type;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('notification_user_' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'form-submitted';
    }
}
