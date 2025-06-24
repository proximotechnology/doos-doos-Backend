<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User_Notify extends Model
{
    use HasFactory;

        protected $fillable = [
        'notify',
        'user_id',
        'is_read',
    ];



    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
