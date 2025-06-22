<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\User;


class Driver_license extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'image',
        'country',
        'state',
        'first_name',
        'last_name',
        'number',
        'birth_date',
        'expiration_date',
    ];


    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }
}
