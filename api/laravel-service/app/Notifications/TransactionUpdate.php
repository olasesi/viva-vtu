<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TransactionUpdate extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public array $data = []
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->data['type'] ?? 'transaction',
            'transaction_id' => $this->data['transaction_id'] ?? null,
            'reference' => $this->data['reference'] ?? null,
            'amount' => $this->data['amount'] ?? null,
            'status' => $this->data['status'] ?? null,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
