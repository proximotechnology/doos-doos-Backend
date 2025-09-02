<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User_Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'price',
        'status',
        'is_paid',
        'car_limite',
        'date_from',
        'date_end',
        'remaining_cars',
        'renewal_data',
        'frontend_success_url',
        'frontend_cancel_url',
        'enable_recurring',
    ];

    // Status constants for easier reference
    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING = 'pending';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELED = 'canceled';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function payment_plan()
    {
        return $this->hasOne(Payment_Plan::class);
    }

    // Helper methods for status checks
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isExpired()
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    // Scope for filtering
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }


    public function paymentPlan()
    {
        return $this->hasOne(PaymentPlan::class);
    }

    public function cars()
    {
        return $this->hasMany(Cars::class, 'user_plan_id'); // شرطة واحدة فقط
    }
}
