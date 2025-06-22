<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cars_Features extends Model
{
    use HasFactory;
    protected $fillable = [
        'cars_id',
        'mileage_range',
        'transmission',
        'mechanical_condition',
        'all_have_seatbelts',
        'num_of_door',
        'num_of_seat',
        'addetinal_features',

    ];

    public function cars()
    {
        return $this->belongsTo(Cars::class , "cars_id");
    }


}
