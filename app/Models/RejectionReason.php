<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectionReason extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_id',
        'reason',

    ];

    public function car()
    {
        return $this->belongsTo(Cars::class ,'car_id');
    }


}
