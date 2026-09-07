<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('provider_reference');
            $table->unsignedTinyInteger('attempts')->default(0)->after('provider');
            $table->string('last_error')->nullable()->after('attempts');
            $table->timestamp('reversed_at')->nullable()->after('completed_at');
            $table->index(['status', 'created_at']);
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['provider']);
            $table->dropColumn(['provider', 'attempts', 'last_error', 'reversed_at']);
        });
    }
};
