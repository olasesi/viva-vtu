<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('kyc_level')->default(0)->after('referred_by');
            $table->string('kyc_bvn_hash', 64)->nullable()->after('kyc_level');
            $table->string('kyc_bvn_last4', 4)->nullable()->after('kyc_bvn_hash');
            $table->string('kyc_nin_hash', 64)->nullable()->after('kyc_bvn_last4');
            $table->string('kyc_nin_last4', 4)->nullable()->after('kyc_nin_hash');
            $table->timestamp('kyc_verified_at')->nullable()->after('kyc_nin_last4');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'kyc_level',
                'kyc_bvn_hash',
                'kyc_bvn_last4',
                'kyc_nin_hash',
                'kyc_nin_last4',
                'kyc_verified_at',
            ]);
        });
    }
};
