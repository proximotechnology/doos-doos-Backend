<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_plan_id',
        'gateway',
        'transaction_id',
        'recurring_token',
        'status',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function userPlan()
    {
        return $this->belongsTo(User_Plan::class);
    }
}
