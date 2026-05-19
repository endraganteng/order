<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_balances', function (Blueprint $table) {
            $table->id();
            $table->string('waiter_id')->unique();
            $table->decimal('balance', 15, 0)->default(0);
            $table->timestamps();

            $table->index('waiter_id');
        });

        Schema::create('payroll_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('waiter_id');
            $table->string('waiter_name')->nullable();
            $table->enum('type', ['salary_credit', 'bonus_credit', 'manual_credit', 'withdrawal']);
            $table->decimal('amount', 15, 0);
            $table->decimal('balance_after', 15, 0)->nullable();
            $table->enum('status', ['completed', 'pending', 'approved', 'rejected'])->default('completed');
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->string('approval_token', 64)->nullable();
            $table->string('reject_reason')->nullable();
            $table->string('created_by')->nullable();
            $table->string('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            $table->index(['waiter_id', 'created_at']);
            $table->index('status');
            $table->index('idempotency_key');
        });

        Schema::create('payroll_idempotency', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('payroll_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_idempotency');
        Schema::dropIfExists('payroll_transactions');
        Schema::dropIfExists('payroll_balances');
        Schema::dropIfExists('payroll_configs');
    }
};
