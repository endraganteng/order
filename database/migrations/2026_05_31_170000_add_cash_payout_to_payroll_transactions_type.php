<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah enum 'cash_payout' ke payroll_transactions.type
     * untuk fitur Bayar Tunai dari Kas Fisik.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE payroll_transactions
            MODIFY COLUMN type ENUM(
                'salary_credit',
                'bonus_credit',
                'manual_credit',
                'cash_payout',
                'withdrawal',
                'migration_credit',
                'kasbon_disbursement',
                'kasbon_deduct'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        // Revert: hapus cash_payout dari enum.
        // Catatan: jika sudah ada baris dengan type=cash_payout, harus diubah dulu sebelum migrate down.
        DB::statement("
            ALTER TABLE payroll_transactions
            MODIFY COLUMN type ENUM(
                'salary_credit',
                'bonus_credit',
                'manual_credit',
                'withdrawal',
                'migration_credit',
                'kasbon_disbursement',
                'kasbon_deduct'
            ) NOT NULL
        ");
    }
};
