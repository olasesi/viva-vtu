<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('transaction_pin_hash')->nullable()->after('password');
            $table->timestamp('transaction_pin_set_at')->nullable()->after('transaction_pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['transaction_pin_hash', 'transaction_pin_set_at']);
        });
    }
};
