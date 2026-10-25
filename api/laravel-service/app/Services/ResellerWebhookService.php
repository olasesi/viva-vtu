<?php

namespace App\Services;

use App\Models\ResellerWebhook;
use App\Models\ResellerWebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ResellerWebhookService
{
    public function dispatchForTransaction($transaction, string $event): void
    {
        if (! in_array($transaction->category, ['airtime', 'data', 'electricity', 'cable', 'education', 'streaming'], true)) {
            return;
        }

        $webhooks = ResellerWebhook::where('user_id', $transaction->user_id)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            $events = $webhook->events;

            if (is_array($events) && $events !== [] && ! in_array($event, $events, true)) {
                continue;
            }

            $payload = [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
                'data' => [
                    'reference' => $transaction->reference,
                    'category' => $transaction->category,
                    'status' => $transaction->status,
                    'amount' => (float) $transaction->amount,
                    'provider' => $transaction->provider,
                    'provider_reference' => $transaction->provider_reference,
                    'completed_at' => $transaction->completed_at?->toIso8601String(),
                ],
            ];

            $body = json_encode($payload);
            $signature = hash_hmac('sha256', $body, $webhook->secret);

            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'X-Viva-Signature' => $signature,
                        'X-Viva-Event' => $event,
                    ])
                    ->post($webhook->url, $payload);

                $webhook->update(['last_sent_at' => now()]);

                ResellerWebhookDelivery::create([
                    'reseller_webhook_id' => $webhook->id,
                    'transaction_id' => $transaction->id,
                    'event' => $event,
                    'status' => $response->successful() ? 'success' : 'failed',
                    'attempts' => 1,
                    'response' => Str::limit((string) $response->body(), 1000),
                    'payload' => $payload,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Reseller webhook dispatch failed', [
                    'webhook_id' => $webhook->id,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);

                ResellerWebhookDelivery::create([
                    'reseller_webhook_id' => $webhook->id,
                    'transaction_id' => $transaction->id,
                    'event' => $event,
                    'status' => 'failed',
                    'attempts' => 1,
                    'response' => Str::limit($e->getMessage(), 1000),
                    'payload' => $payload,
                ]);
            }
        }
    }
}
