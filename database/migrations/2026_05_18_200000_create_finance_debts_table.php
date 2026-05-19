<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_debts', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name');
            $table->decimal('amount', 15, 0); // jumlah awal hutang
            $table->decimal('paid', 15, 0)->default(0); // total sudah dibayar
            $table->string('description')->nullable();
            $table->date('debt_date'); // tanggal hutang
            $table->date('due_date')->nullable(); // jatuh tempo
            $table->enum('status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->timestamps();
        });

        Schema::create('finance_debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_debt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_account_id')->constrained();
            $table->decimal('amount', 15, 0);
            $table->date('payment_date');
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_debt_payments');
        Schema::dropIfExists('finance_debts');
    }
};
