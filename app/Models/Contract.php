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
        'contract_items' // إضافة هذا الحقل

    ];

    protected $casts = [
        'contract_items' => 'array' // لتحويل JSON تلقائياً إلى array
    ];


    public function booking()
    {
        return $this->belongsTo(Order_Booking::class, 'order_booking_id');
    }


}
