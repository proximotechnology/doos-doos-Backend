<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class profile extends Model
{
    use HasFactory;
    protected $fillable = [
        'first_name',
        'last_name',
        'user_id',
        'address_1',
        'address_2',
        'zip_code',
        'city',
        'image',
    ];
}
