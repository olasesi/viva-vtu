<?php

namespace App\Services;

use App\Mail\TransactionReceipt;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReceiptService
{
    public function send(Transaction $transaction, string $event): void
    {
        if (! config('receipts.email.enabled', true)) {
            return;
        }

        $user = $transaction->user;

        if (! $user || ! $user->email) {
            return;
        }

        $settings = app(SettingService::class)->defaultsMergedWithStored('email');
        $from = $settings['mail_from_address'] ?? null;

        if (! $from) {
            Log::info('Receipt skipped: mail_from_address not configured', [
                'reference' => $transaction->reference,
                'event' => $event,
            ]);

            return;
        }

        $this->bindMailTransport($settings);

        Mail::to($user->email)->send(new TransactionReceipt($transaction, $user, $event));
    }

    protected function bindMailTransport(array $settings): void
    {
        config()->set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $settings['mail_host'] ?? 'localhost',
            'mail.mailers.smtp.port' => (int) ($settings['mail_port'] ?? 587),
            'mail.mailers.smtp.username' => $settings['mail_username'] ?? null,
            'mail.mailers.smtp.password' => $settings['mail_password'] ?? null,
            'mail.mailers.smtp.encryption' => $settings['mail_encryption'] ?? 'tls',
            'mail.from.address' => $settings['mail_from_address'],
            'mail.from.name' => $settings['mail_from_name'] ?? 'VIVAVTU',
        ]);
    }
}
