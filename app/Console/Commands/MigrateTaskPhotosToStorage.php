<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use App\Services\WaiterTaskFirebaseService;
use Illuminate\Console\Command;

/**
 * MigrateTaskPhotosToStorage
 *
 * Pindahkan foto base64 lama di /waiter_tasks ke Firebase Storage, ganti
 * dengan URL. Idempotent: entry yang sudah http dilewati. --dry-run default.
 *
 * Usage:
 *   php artisan tasks:migrate-photos --dry-run --from=2026-05-01 --to=2026-06-06
 *   php artisan tasks:migrate-photos --from=2026-05-01 --to=2026-06-06
 */
class MigrateTaskPhotosToStorage extends Command
{
    protected $signature = 'tasks:migrate-photos
                            {--dry-run : Hitung saja, tidak upload/tulis}
                            {--from= : Mulai YYYY-MM-DD (default 30 hari lalu)}
                            {--to= : Akhir YYYY-MM-DD (default hari ini)}';

    protected $description = 'Migrasi foto base64 task lama ke Firebase Storage';

    public function handle(FirebaseService $firebase, WaiterTaskFirebaseService $waiterTask): int
    {
        $from = $this->option('from') ?: now()->subDays(30)->format('Y-m-d');
        $to = $this->option('to') ?: now()->format('Y-m-d');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Migrasi foto task [{$from}..{$to}]".($dryRun ? ' (DRY-RUN)' : ''));

        $r = $waiterTask->migrateTaskPhotosToStorage($from, $to, $dryRun);

        $this->info("Scanned: {$r['scanned']} | Migrated: {$r['migrated']} | Failed: {$r['failed']} | Skipped: {$r['skipped']}");

        if ($r['failed'] > 0) {
            $this->warn('Ada foto gagal upload. Cek log. Aman diulang (idempotent).');
        }

        return Command::SUCCESS;
    }
}
