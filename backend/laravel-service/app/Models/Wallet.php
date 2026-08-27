<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance',
        'currency',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function credit(float $amount, string $reference, string $description = ''): bool
    {
        return DB::transaction(function () use ($amount, $reference, $description) {
            $locked = DB::table('wallets')
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return false;
            }

            $newBalance = (float) $locked->balance + $amount;

            DB::table('wallets')
                ->where('id', $this->id)
                ->update(['balance' => $newBalance, 'updated_at' => now()]);

            Transaction::create([
                'user_id' => $this->user_id,
                'wallet_id' => $this->id,
                'type' => 'credit',
                'category' => 'wallet_fund',
                'reference' => $reference,
                'description' => $description,
                'amount' => $amount,
                'status' => 'successful',
                'completed_at' => now(),
            ]);

            $this->balance = $newBalance;

            return true;
        });
    }

    public function debit(float $amount, string $reference, string $description = ''): bool
    {
        return DB::transaction(function () use ($amount, $reference, $description) {
            $locked = DB::table('wallets')
                ->where('id', $this->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return false;
            }

            if ((float) $locked->balance < $amount) {
                return false;
            }

            $newBalance = (float) $locked->balance - $amount;

            DB::table('wallets')
                ->where('id', $this->id)
                ->update(['balance' => $newBalance, 'updated_at' => now()]);

            Transaction::create([
                'user_id' => $this->user_id,
                'wallet_id' => $this->id,
                'type' => 'debit',
                'category' => 'wallet_fund',
                'reference' => $reference,
                'description' => $description,
                'amount' => $amount,
                'status' => 'successful',
                'completed_at' => now(),
            ]);

            $this->balance = $newBalance;

            return true;
        });
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2);
    }
}
