<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reseller_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('url', 500);
            $table->string('secret', 64);
            $table->boolean('is_active')->default(true);
            $table->json('events')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });

        Schema::create('reseller_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('reseller_webhook_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->string('event', 32);
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->text('response')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('reseller_webhook_id')->references('id')->on('reseller_webhooks')->cascadeOnDelete();
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->index(['reseller_webhook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_webhook_deliveries');
        Schema::dropIfExists('reseller_webhooks');
    }
};
