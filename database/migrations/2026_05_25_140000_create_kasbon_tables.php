<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasbon_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('kasbons', function (Blueprint $table) {
            $table->id();
            $table->string('waiter_id');
            $table->string('waiter_name')->nullable();
            $table->decimal('amount', 15, 0);
            $table->decimal('remaining', 15, 0);
            $table->text('reason')->nullable();
            $table->enum('status', ['active', 'paid_off', 'cancelled', 'written_off'])->default('active');
            $table->string('created_by')->nullable();
            $table->timestamp('paid_off_at')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('written_off_by')->nullable();
            $table->timestamp('written_off_at')->nullable();
            $table->text('written_off_reason')->nullable();
            $table->timestamps();

            $table->index(['waiter_id', 'status']);
            $table->index('status');
        });

        Schema::create('kasbon_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kasbon_id');
            $table->string('waiter_id');
            $table->decimal('amount', 15, 0);
            $table->decimal('remaining_after', 15, 0);
            $table->enum('source', ['auto_deduct', 'manual_payment'])->default('auto_deduct');
            $table->unsignedBigInteger('payroll_tx_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('kasbon_id')->references('id')->on('kasbons')->onDelete('cascade');
            $table->index(['kasbon_id', 'created_at']);
            $table->index('waiter_id');
        });

        // Alter payroll_transactions.type to add kasbon types
        DB::statement("ALTER TABLE payroll_transactions MODIFY COLUMN `type` ENUM('salary_credit', 'bonus_credit', 'manual_credit', 'withdrawal', 'kasbon_disbursement', 'kasbon_deduct') NOT NULL");

        // Seed default configs
        DB::table('kasbon_configs')->insert([
            ['key' => 'default_limit_percent', 'value' => '30', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'kasbon_limit_fixed', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'min_kasbon_amount', 'value' => '50000', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'max_active_kasbon', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'auto_deduct_enabled', 'value' => '1', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('kasbon_payments');
        Schema::dropIfExists('kasbons');
        Schema::dropIfExists('kasbon_configs');

        // Revert payroll_transactions.type
        DB::statement("ALTER TABLE payroll_transactions MODIFY COLUMN `type` ENUM('salary_credit', 'bonus_credit', 'manual_credit', 'withdrawal') NOT NULL");
    }
};
