<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;
        protected $fillable = [
        'name',
        'price',
        'car_limite',
        'count_day',
        'is_active',
    ];


    public function user_plan()
    {
        return $this->hasMany(User_Plan::class);
    }

}
