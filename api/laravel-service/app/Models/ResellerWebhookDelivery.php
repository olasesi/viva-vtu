<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResellerWebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'reseller_webhook_id',
        'transaction_id',
        'event',
        'status',
        'attempts',
        'response',
        'payload',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'payload' => 'array',
    ];

    public function webhook()
    {
        return $this->belongsTo(ResellerWebhook::class, 'reseller_webhook_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
