<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Represen_Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order__booking_id',
        'representative_id',
        'status'
    ];



    public function order_booking()
    {
        return $this->belongsTo(Order_Booking::class , 'order__booking_id');
    }


    public function representative()
    {
        return $this->belongsTo(Representative::class , 'representative_id');
    }

}
