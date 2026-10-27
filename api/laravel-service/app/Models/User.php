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
        'reseller_parent_id',
        'reseller_margin_rate',
        'is_reseller',
        'kyc_level',
        'kyc_bvn_hash',
        'kyc_bvn_last4',
        'kyc_nin_hash',
        'kyc_nin_last4',
        'kyc_verified_at',
        'transaction_pin_hash',
        'transaction_pin_set_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'kyc_bvn_hash',
        'kyc_nin_hash',
        'transaction_pin_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'is_reseller' => 'boolean',
        'reseller_margin_rate' => 'float',
        'kyc_level' => 'integer',
        'kyc_verified_at' => 'datetime',
        'transaction_pin_set_at' => 'datetime',
    ];

    public function hasTransactionPin(): bool
    {
        return ! empty($this->transaction_pin_hash);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function beneficiaries()
    {
        return $this->hasMany(Beneficiary::class);
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

    public function resellerParent()
    {
        return $this->belongsTo(User::class, 'reseller_parent_id');
    }

    public function downline()
    {
        return $this->hasMany(User::class, 'reseller_parent_id');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
