<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_debts', function (Blueprint $table) {
            $table->unsignedBigInteger('finance_category_id')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('finance_debts', function (Blueprint $table) {
            $table->dropColumn('finance_category_id');
        });
    }
};
