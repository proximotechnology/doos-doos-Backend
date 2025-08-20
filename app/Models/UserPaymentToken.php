<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPaymentToken extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'token',
        'init_trans_id',
        'payment_method',
        'is_default',
        'card_last_four',
        'card_brand',
        'expiry_date'
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
