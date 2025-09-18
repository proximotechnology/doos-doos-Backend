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
        'car_model_id',
        'brand_id',
        'model_year_id',
        'extenal_image',
        'address_return',
        'status',
        'price',
        'day',
        'lang',
        'lat',
        'lat_return',
        'lang_return',
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
        'user_plan_id',
        'max_day_trip',
        'driver_available', // تم إضافة الحقل الجديد

    ];




    public function owner()
    {
        return $this->belongsTo(User::class, "owner_id");
    }



    public function model()
    {
        return $this->belongsTo(CarModel::class, "car_model_id");
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, "brand_id");
    }


    public function years()
    {
        return $this->belongsTo(ModelYear::class, "model_year_id");
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


    public function user_plan()
    {
        return $this->belongsTo(User_Plan::class, 'user_plan_id');
    }


    public function rejectionReasons()
    {
        return $this->hasMany(RejectionReason::class ,'car_id');
    }


}
