<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cars_Image extends Model
{
    use HasFactory;

        protected $fillable = [
        'cars_id',
        'image',
    ];



    public function owner()
    {
        return $this->belongsTo(Cars::class , "cars_id");
    }

}
