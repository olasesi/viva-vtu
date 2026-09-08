<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'reference',
        'status',
        'amount',
        'payload',
        'admin_note',
        'fulfilled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'json',
        'fulfilled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
