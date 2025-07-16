<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Representative extends Model
{
    use HasFactory;

     protected $fillable = [
        'user_id',
        'status',

    ];



    public function user()
    {
        return $this->belongsTo(User::class , 'user_id');
    }


    public function represen_order()
    {
        return $this->hasMany(Represen_Order::class);
    }
}
