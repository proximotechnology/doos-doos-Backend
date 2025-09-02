<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeaturePlans extends Model
{
    use HasFactory;

    protected $fillable = ['feature' ,'plan_id']; // Add this line



    public function plan()
    {
        return $this->belongsTo(Plan::class ,'plan_id');
    }
}
