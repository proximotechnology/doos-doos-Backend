<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cars extends Model
{
    use HasFactory;
    protected $fillable = [
        'make',
        'owner_id',
        'model_car_id',
        'brand_car_id',
        'year',
        'status',
        'price',
        'day',
        'lang',
        'lat',
        'address',
        'description',
        'number',
        'vin',
        'is_paid',
        'image_license',
        'number_license',
        'state',
        'description_condition',
        'advanced_notice',
        'min_day_trip',
        'max_day_trip',
    ];




    public function owner()
    {
        return $this->belongsTo(User::class, "owner_id");
    }



    public function model()
    {
        return $this->belongsTo(ModelCars::class, "model_car_id");
    }

    public function brand()
    {
        return $this->belongsTo(BrandCar::class, "brand_car_id");
    }


    public function cars_features()
    {
        return $this->hasOne(Cars_Features::class);
    }

    public function car_image()
    {
        return $this->hasMany(Cars_Image::class);
    }

    public function booking()
    {
        return $this->hasMany(Order_Booking::class);
    }
}
