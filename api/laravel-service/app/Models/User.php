<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'is_active',
        'role',
        'referral_code',
        'referred_by',
        'kyc_level',
        'kyc_bvn_hash',
        'kyc_bvn_last4',
        'kyc_nin_hash',
        'kyc_nin_last4',
        'kyc_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'kyc_bvn_hash',
        'kyc_nin_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'kyc_level' => 'integer',
        'kyc_verified_at' => 'datetime',
    ];

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class);
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
