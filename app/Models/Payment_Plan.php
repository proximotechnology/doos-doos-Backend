<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment_Plan extends Model
{
    use HasFactory;
    protected $table = 'payment_plans'; // تأكد من هذا الاسم

       protected $fillable = [
        'user_plan_id',
        'user_id',
        'payment_method',
        'amount',
        'status',
        'transaction_id',
        'payment_details',
        'paid_at'
    ];

    protected $casts = [
        'payment_details' => 'array',
        'paid_at' => 'datetime'
    ];

    public function user_plan()
    {
        return $this->belongsTo(User_Plan::class, 'user_plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
