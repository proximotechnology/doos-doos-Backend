<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalReminder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // public $message;
    // public $broadcastQueue = null;

    // public function __construct($message)
    // {
    //     $this->message = $message;
    // }

    // public function broadcastOn()
    // {
    //     return ['subscribe-channel'];
    // }

    // public function broadcastAs()
    // {
    //     return 'subscribe-submitted';
    // }

    // public function broadcastWith()
    // {
    //     return ['message' => $this->message];
    // }


    public $message;
    public $type;

    public function __construct($message, $type)
    {
        $this->message = $message;
        $this->type = $type;
    }

    public function broadcastOn()
    {
        return ['notify-channel'];
    }

    public function broadcastAs()
    {
        return 'form-submitted';
    }
}
