<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User_Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'price',
        'status',
        'is_paid',
        'car_limite',
        'date_from',
        'date_end',
        'remaining_cars',
    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }


    public function payment_plan()
    {
        return $this->hasOne(Payment_Plan::class);
    }
}
