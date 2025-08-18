<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_booking_id',
        'otp_user',
        'otp_renter',
        'status',
    ];



    public function booking()
    {
        return $this->belongsTo(Order_Booking::class, 'order_booking_id');
    }


}
