<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('transaction_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('gateway', ['paystack', 'flutterwave', 'vtpass']);
            $table->string('event_type');
            $table->json('payload');
            $table->json('response');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['gateway', 'created_at']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
