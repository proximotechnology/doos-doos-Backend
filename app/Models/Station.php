<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;


     protected $fillable = [
        'name',
        'lat',
        'lang'
    ];


    public function order_booking()
    {
        return $this->hasMany(Order_Booking::class);
    }

}
