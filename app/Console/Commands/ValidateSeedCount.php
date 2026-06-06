<?php

namespace App\Console\Commands;

use App\Models\WaiterTask;
use Illuminate\Console\Command;
use Kreait\Firebase\Contract\Database;

/**
 * ValidateSeedCount (plan 18.1 + koreksi 19.4)
 *
 * Bandingkan jumlah waiter_tasks Firebase vs MySQL pada RENTANG TANGGAL SAMA
 * (bukan total global — Firebase simpan history panjang, perbandingan total
 * akan selalu BLOCK secara salah).
 *
 * Gate aktivasi feature flag:
 *   selisih > 1%  -> BLOCK
 *   selisih 0-1%  -> investigasi manual
 *   selisih 0%    -> approve
 *
 * Usage:
 *   php artisan seed:validate-count
 *   php artisan seed:validate-count --from=2026-05-01 --to=2026-06-06
 */
class ValidateSeedCount extends Command
{
    protected $signature = 'seed:validate-count
                            {--from= : Mulai tanggal YYYY-MM-DD (default 30 hari lalu)}
                            {--to= : Akhir tanggal YYYY-MM-DD (default hari ini)}';

    protected $description = 'Validasi konsistensi count Firebase vs MySQL waiter_tasks pada rentang sama';

    public function handle(Database $database): int
    {
        $from = $this->option('from') ?: now()->subDays(30)->format('Y-m-d');
        $to = $this->option('to') ?: now()->format('Y-m-d');

        // Firebase: hitung HANYA dalam rentang sama (butuh .indexOn scheduled_for_date)
        $snapshot = $database->getReference('waiter_tasks')
            ->orderByChild('scheduled_for_date')
            ->startAt($from)
            ->endAt($to)
            ->getSnapshot();
        $firebaseCount = $snapshot->numChildren();

        // MySQL: rentang sama
        $mysqlCount = WaiterTask::query()
            ->whereBetween('scheduled_for_date', [$from, $to])
            ->count();

        $diff = abs($firebaseCount - $mysqlCount);
        $diffPercent = $firebaseCount > 0 ? round(($diff / $firebaseCount) * 100, 2) : 0.0;

        $this->info("[{$from}..{$to}] Firebase: {$firebaseCount} | MySQL: {$mysqlCount} | Diff: {$diff} ({$diffPercent}%)");

        if ($diffPercent > 1) {
            $this->error('BLOCKED: Selisih > 1% pada rentang sama. Jangan aktifkan feature flag.');
            $this->error('Investigasi record yang hilang sebelum lanjut.');
            return Command::FAILURE;
        }

        if ($diffPercent > 0) {
            $this->warn('PASSED dengan catatan: selisih 0-1%. Investigasi manual disarankan sebelum approve.');
            return Command::SUCCESS;
        }

        $this->info('PASSED: Data konsisten. Feature flag boleh diaktifkan.');
        return Command::SUCCESS;
    }
}
