<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'country',
        'phone',
        'has_license',
        'is_company',
        'otp',
        'email_verified_at',
        'type',
        'has_car',
        'montypay_recurring_token',
        'montypay_init_trans_id ',

    ];


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted()
    {
        static::created(function ($user) {
            // فقط للي type = 0 (User أو Owner)
            if ($user->type == 0) {
                $user->wallet()->create([
                    'balance' => 0, // الرصيد الابتدائي صفر
                ]);
            }
        });
    }


    public function cars()
    {
        return $this->hasMany(Cars::class, 'owner_id');
    }

    public function profile()
    {
        return $this->hasOne(profile::class);
    }

    public function driver_license()
    {
        return $this->hasOne(Driver_license::class);
    }


    public function company()
    {
        return $this->hasOne(Company::class);
    }

    public function representative()
    {
        return $this->hasOne(Representative::class);
    }


    public function UserPaymentToken()
    {
        return $this->hasMany(UserPaymentToken::class);
    }

    public function order_booking()
    {
        return $this->hasMany(Order_Booking::class);
    }


    public function notifecation()
    {
        return $this->hasMany(User_Notify::class);
    }

    public function user_plan()
    {
        return $this->hasMany(User_Plan::class);
    }

    public function payment()
    {
        return $this->hasMany(Payment::class);
    }


    public function payment_plan()
    {
        return $this->hasMany(PaymentPlan::class);
    }





    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
}
