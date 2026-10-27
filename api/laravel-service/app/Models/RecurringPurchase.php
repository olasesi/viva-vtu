<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'category',
        'payload',
        'amount',
        'frequency',
        'interval',
        'day_of_month',
        'status',
        'next_run_at',
        'last_run_at',
        'last_status',
        'last_error',
        'run_count',
        'failure_count',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'decimal:2',
        'interval' => 'integer',
        'day_of_month' => 'integer',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'run_count' => 'integer',
        'failure_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }
}
