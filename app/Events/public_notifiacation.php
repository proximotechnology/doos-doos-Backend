<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class public_notifiacation implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    public $message;
    public $type;

    public function __construct($message, $type)
    {
        $this->message = $message;
        $this->type = $type;
    }

    public function broadcastOn()
    {
        return ['public-channel'];
    }

    public function broadcastAs()
    {
        return 'form-submitted';
    }
}
