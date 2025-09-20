<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarModel extends Model
{
    use HasFactory;


    protected $fillable = ['brand_id', 'name'];

    public function brand()
    {
        return $this->belongsTo(Brand::class ,'brand_id');
    }

    public function years()
    {
        return $this->hasMany(ModelYear::class);
    }

    public function car()
    {
        return $this->hasMany(Cars::class);
    }
}
