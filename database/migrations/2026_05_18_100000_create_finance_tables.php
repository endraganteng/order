<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Finance Settings (domain API, token, jadwal sync dll)
        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Finance Categories
        Schema::create('finance_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['income', 'expense']);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('finance_categories')->nullOnDelete();
        });

        // Finance Allocations
        Schema::create('finance_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_category_id')->constrained()->cascadeOnDelete();
            $table->decimal('percentage', 8, 2);
            $table->date('effective_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Cash Accounts
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('balance', 15, 0)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Cash Transfers
        Schema::create('cash_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_account_id')->constrained('cash_accounts');
            $table->foreignId('to_account_id')->constrained('cash_accounts');
            $table->decimal('amount', 15, 0);
            $table->decimal('fee', 15, 0)->default(0);
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // Cash Mutations
        Schema::create('cash_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained();
            $table->foreignId('finance_category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['income', 'expense', 'transfer_in', 'transfer_out']);
            $table->decimal('amount', 15, 0);
            $table->decimal('balance_after', 15, 0);
            $table->string('description');
            $table->string('reference_type')->nullable(); // sync, transfer, manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->date('transaction_date');
            $table->timestamps();

            $table->index(['cash_account_id', 'transaction_date']);
            $table->index(['transaction_date']);
        });

        // Finance Sync Logs
        Schema::create('finance_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['daily', 'manual', 'auto', 'retry']);
            $table->date('sync_date_from');
            $table->date('sync_date_to');
            $table->enum('status', ['success', 'failed', 'partial_success', 'need_review']);
            $table->integer('records_synced')->default(0);
            $table->integer('records_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->json('details')->nullable();
            $table->string('triggered_by')->nullable();
            $table->integer('duration_ms')->nullable();
            $table->timestamps();
        });

        // Finance API Mappings
        Schema::create('finance_api_mappings', function (Blueprint $table) {
            $table->id();
            $table->enum('mapping_type', ['category', 'cash_account']);
            $table->string('api_key'); // e.g. line_type:product, source:penjualan_tunai
            $table->string('api_value');
            $table->unsignedBigInteger('target_id'); // finance_category_id or cash_account_id
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['mapping_type', 'api_key', 'api_value']);
        });

        // Finance Audit Logs
        Schema::create('finance_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('user_name');
            $table->string('user_role')->nullable();
            $table->string('action'); // create, update, delete, sync, approve, reject
            $table->string('module'); // category, allocation, cash_account, transfer, sync, mapping, settings
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['module', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        // Finance Daily Data (synced from API)
        Schema::create('finance_daily_data', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->decimal('penjualan_tunai', 15, 0)->default(0);
            $table->decimal('penjualan_qris', 15, 0)->default(0);
            $table->decimal('total_pendapatan', 15, 0)->default(0);
            $table->decimal('total_pengeluaran', 15, 0)->default(0);
            $table->decimal('pendapatan_bersih', 15, 0)->default(0);
            $table->integer('jumlah_shift')->default(0);
            $table->enum('sync_status', ['synced', 'need_review', 'failed'])->default('synced');
            $table->timestamps();
        });

        // Finance Expense Items (synced from API)
        Schema::create('finance_expense_items', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('line_type'); // product, kasbon, custom
            $table->string('deskripsi');
            $table->string('kategori')->nullable();
            $table->string('supplier')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('harga_satuan', 15, 0)->default(0);
            $table->decimal('total', 15, 0)->default(0);
            $table->string('hash')->unique(); // dedup key
            $table->foreignId('finance_category_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['synced', 'need_review', 'ignored'])->default('synced');
            $table->timestamps();

            $table->index(['tanggal']);
            $table->index(['status']);
        });

        // Finance Shifts (synced from API)
        Schema::create('finance_shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_shift_id')->unique();
            $table->date('tanggal');
            $table->integer('shift_number');
            $table->string('loket')->nullable();
            $table->string('kasir')->nullable();
            $table->decimal('penjualan_tunai', 15, 0)->default(0);
            $table->decimal('penjualan_qris', 15, 0)->default(0);
            $table->decimal('total_pengeluaran', 15, 0)->default(0);
            $table->decimal('selisih', 15, 0)->default(0);
            $table->string('status')->nullable();
            $table->timestamps();

            $table->index(['tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_shifts');
        Schema::dropIfExists('finance_expense_items');
        Schema::dropIfExists('finance_daily_data');
        Schema::dropIfExists('finance_audit_logs');
        Schema::dropIfExists('finance_api_mappings');
        Schema::dropIfExists('finance_sync_logs');
        Schema::dropIfExists('cash_mutations');
        Schema::dropIfExists('cash_transfers');
        Schema::dropIfExists('cash_accounts');
        Schema::dropIfExists('finance_allocations');
        Schema::dropIfExists('finance_categories');
        Schema::dropIfExists('finance_settings');
    }
};
