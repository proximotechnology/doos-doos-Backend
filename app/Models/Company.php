<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
        protected $fillable = [
        'user_id',
        'legal_name',
        'num_of_employees',
        'is_under_vat',
        'vat_num',
        'zip_code',
        'country',
        'address_1',
        'address_2',
        'city'
    ];



    public function user()
    {
        return $this->belongsTo(User::class , 'user_id');
    }
}
