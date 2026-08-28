<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['credit', 'debit']);
            $table->enum('category', ['airtime', 'data', 'electricity', 'cable', 'wallet_fund', 'referral']);
            $table->string('reference')->unique();
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('fee', 12, 2)->default(0);
            $table->enum('status', ['pending', 'successful', 'failed', 'reversed'])->default('pending');
            $table->string('provider_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'category']);
            $table->index('reference');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
