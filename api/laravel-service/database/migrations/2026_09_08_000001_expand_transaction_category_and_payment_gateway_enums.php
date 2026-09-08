<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('category', ['airtime', 'data', 'electricity', 'cable', 'education', 'streaming', 'wallet_fund', 'referral'])->change();
        });

        Schema::table('payment_logs', function (Blueprint $table) {
            $table->enum('gateway', ['paystack', 'flutterwave', 'vtpass', 'aidapay', 'easyaccess'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('category', ['airtime', 'data', 'electricity', 'cable', 'wallet_fund', 'referral'])->change();
        });

        Schema::table('payment_logs', function (Blueprint $table) {
            $table->enum('gateway', ['paystack', 'flutterwave', 'vtpass'])->change();
        });
    }
};
