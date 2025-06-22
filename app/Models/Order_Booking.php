<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order_Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'car_id',
        'date_from',
        'date_end',
        'total_price',
        'is_paid',
        'with_driver',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function car()
    {
        return $this->belongsTo(Cars::class, "car_id");
    }

    public function car_details()
    {
        return $this->belongsTo(Cars::class, "car_id")->select('id', 'make', 'model', 'year');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class , 'order_booking_id');
    }
}
