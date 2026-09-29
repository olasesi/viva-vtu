<?php

namespace App\Mail;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TransactionReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public User $user,
        public string $event,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.receipt',
            with: [
                'transaction' => $this->transaction,
                'user' => $this->user,
                'event' => $this->event,
                'category' => $this->categoryLabel(),
                'amount' => number_format((float) $this->transaction->amount, 2),
                'title' => $this->title(),
                'lede' => $this->lede(),
            ],
        );
    }

    protected function subjectLine(): string
    {
        return match ($this->event) {
            'refund' => 'Refund processed for '.strtolower($this->categoryLabel()),
            'funded' => 'Your VIVAVTU wallet has been funded',
            'received' => 'You received a credit to your VIVAVTU wallet',
            default => 'Receipt for your '.strtolower($this->categoryLabel()),
        };
    }

    protected function title(): string
    {
        return match ($this->event) {
            'refund' => 'Refund Processed',
            'funded' => 'Wallet Funded',
            'received' => 'Money Received',
            default => 'Payment Successful',
        };
    }

    protected function lede(): string
    {
        $category = strtolower($this->categoryLabel());
        $amount = '₦'.number_format((float) $this->transaction->amount, 2);

        return match ($this->event) {
            'refund' => "Your {$category} payment of {$amount} could not be completed and has been refunded to your wallet.",
            'funded' => "Your wallet was credited with {$amount}.",
            'received' => "You received {$amount} into your wallet ({$category}).",
            default => "Your {$category} order of {$amount} has been completed successfully.",
        };
    }

    protected function categoryLabel(): string
    {
        return match ($this->transaction->category) {
            'airtime' => 'Airtime',
            'data' => 'Data',
            'electricity' => 'Electricity',
            'cable' => 'Cable TV',
            'education' => 'Exam Pin',
            'streaming' => 'Streaming',
            'transfer' => 'Transfer',
            'wallet_fund' => 'Wallet Funding',
            'referral' => 'Referral Bonus',
            default => ucfirst((string) $this->transaction->category),
        };
    }
}
