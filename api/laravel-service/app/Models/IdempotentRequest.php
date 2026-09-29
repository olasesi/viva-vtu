<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotentRequest extends Model
{
    use HasFactory;

    protected $table = 'idempotency_keys';

    protected $fillable = [
        'user_id',
        'idempotency_key',
        'reference',
        'status',
        'response',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
