<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_daily_data', function (Blueprint $table) {
            $table->decimal('total_retur', 15, 0)->default(0)->after('total_pendapatan');
            $table->decimal('total_pengeluaran_shift', 15, 0)->default(0)->after('total_pengeluaran');
        });
    }

    public function down(): void
    {
        Schema::table('finance_daily_data', function (Blueprint $table) {
            $table->dropColumn(['total_retur', 'total_pengeluaran_shift']);
        });
    }
};
