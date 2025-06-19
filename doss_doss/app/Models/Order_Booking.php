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
        'expire_paid_date',
        'status',

    ];


    public function user()
    {
        return $this->belongsTo(User::class , "user_id");
    }


    public function car()
    {
        return $this->belongsTo(Cars::class , "car_id");
    }


}
