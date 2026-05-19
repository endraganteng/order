<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_shifts', function (Blueprint $table) {
            $table->decimal('modal_awal', 15, 0)->default(0)->after('kasir');
        });
    }

    public function down(): void
    {
        Schema::table('finance_shifts', function (Blueprint $table) {
            $table->dropColumn('modal_awal');
        });
    }
};
