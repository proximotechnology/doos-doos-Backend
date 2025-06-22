<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_booking_id',
        'user_id',
        'payment_method',
        'amount',
        'status',
        'transaction_id',
        'payment_details',
        'paid_at'
    ];

    protected $casts = [
        'payment_details' => 'array',
        'paid_at' => 'datetime'
    ];

    public function booking()
    {
        return $this->belongsTo(Order_Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
