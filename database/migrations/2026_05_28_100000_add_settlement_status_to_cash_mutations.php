<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_mutations', function (Blueprint $table) {
            $table->enum('settlement_status', ['settled', 'pending'])->default('settled')->after('transaction_date');
            $table->timestamp('settled_at')->nullable()->after('settlement_status');
            $table->index(['settlement_status', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::table('cash_mutations', function (Blueprint $table) {
            $table->dropIndex(['settlement_status', 'transaction_date']);
            $table->dropColumn(['settlement_status', 'settled_at']);
        });
    }
};
