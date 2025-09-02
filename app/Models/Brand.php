<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasFactory;


    protected $fillable = ['make_id', 'name', 'country'];

    public function models()
    {
        return $this->hasMany(CarModel::class);
    }
}
