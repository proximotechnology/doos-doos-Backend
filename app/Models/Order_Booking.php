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
        'payment_method',
        'is_paid',
        'with_driver',
        'status',
        'repres_status',
        'station_id',
        'driver_type',
        'has_representative',
        'completed_at',
        'zip_code',



    ];


    public function user()
    {
        return $this->belongsTo(User::class , "user_id");
    }


    public function car()
    {
        return $this->belongsTo(Cars::class , "car_id");
    }


    public function station()
    {
        return $this->belongsTo(Station::class , "staion_id");
    }



    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function car_details()
    {
        return $this->belongsTo(Cars::class , "car_id");
    }

    public function represen_order()
    {
        return $this->hasMany(Represen_Order::class);
    }


    public function contract()
    {
        return $this->hasOne(Contract::class);
    }

}
