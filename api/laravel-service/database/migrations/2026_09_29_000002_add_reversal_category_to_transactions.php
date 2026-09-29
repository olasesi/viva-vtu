<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('category', ['airtime', 'data', 'electricity', 'cable', 'education', 'streaming', 'wallet_fund', 'referral', 'transfer', 'reversal'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('category', ['airtime', 'data', 'electricity', 'cable', 'education', 'streaming', 'wallet_fund', 'referral', 'transfer'])->change();
        });
    }
};
