<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('reseller_parent_id')->nullable()->after('referred_by');
            $table->decimal('reseller_margin_rate', 5, 4)->default(0.0000)->after('reseller_parent_id');
            $table->boolean('is_reseller')->default(false)->after('reseller_margin_rate');

            $table->foreign('reseller_parent_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['reseller_parent_id']);
            $table->dropColumn(['reseller_parent_id', 'reseller_margin_rate', 'is_reseller']);
        });
    }
};
