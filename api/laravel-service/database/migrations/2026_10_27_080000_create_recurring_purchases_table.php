<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->enum('category', ['airtime', 'data', 'electricity', 'cable', 'education', 'streaming']);
            $table->json('payload');
            $table->decimal('amount', 15, 2);
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('monthly');
            $table->unsignedSmallInteger('interval')->default(1);
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active');
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['status', 'next_run_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_purchases');
    }
};
