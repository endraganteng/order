<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FinanceSettleQris extends Command
{
    protected $signature = 'finance:settle-qris {--date= : Tanggal yang mau di-settle (default: hari ini)}';
    protected $description = 'Settle pending QRIS mutations — update saldo akun dan mark as settled';

    public function handle(): int
    {
        $date = $this->option('date') ?? now()->format('Y-m-d');

        $pendingMutations = DB::table('cash_mutations')
            ->where('settlement_status', 'pending')
            ->where('transaction_date', $date)
            ->get();

        if ($pendingMutations->isEmpty()) {
            $this->info("Tidak ada QRIS pending untuk tanggal {$date}.");
            return self::SUCCESS;
        }

        $totalSettled = 0;
        $totalAmount = 0;

        DB::transaction(function () use ($pendingMutations, &$totalSettled, &$totalAmount) {
            foreach ($pendingMutations as $mutation) {
                // Update saldo akun
                if ($mutation->type === 'income' || $mutation->type === 'transfer_in') {
                    DB::table('cash_accounts')->where('id', $mutation->cash_account_id)->increment('balance', $mutation->amount);
                } else {
                    DB::table('cash_accounts')->where('id', $mutation->cash_account_id)->decrement('balance', $mutation->amount);
                }

                // Update balance_after untuk mutasi ini
                $newBalance = (int) DB::table('cash_accounts')->where('id', $mutation->cash_account_id)->value('balance');

                // Mark as settled
                DB::table('cash_mutations')->where('id', $mutation->id)->update([
                    'settlement_status' => 'settled',
                    'settled_at' => now(),
                    'balance_after' => $newBalance,
                    'updated_at' => now(),
                ]);

                $totalSettled++;
                $totalAmount += $mutation->amount;
            }
        });

        $formattedAmount = number_format($totalAmount, 0, ',', '.');
        $this->info("✅ Settled {$totalSettled} mutasi QRIS untuk {$date} (total: Rp {$formattedAmount})");
        Log::info("QRIS settlement: {$totalSettled} mutations settled for {$date}, total: {$totalAmount}");

        // Notifikasi ke Telegram Finance
        try {
            $msg = "✅ *QRIS SETTLED*\n━━━━━━━━━━━━━━━━━━━━━\n📅 Tanggal: {$date}\n💰 Total: Rp {$formattedAmount}\n📊 Jumlah transaksi: {$totalSettled}\n⏰ Dana sudah masuk ke saldo";
            app(\App\Services\TelegramService::class)->sendToFinance($msg);
        } catch (\Exception $e) {
            Log::warning('QRIS settle telegram notification failed: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}
