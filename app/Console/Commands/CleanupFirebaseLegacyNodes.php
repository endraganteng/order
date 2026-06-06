<?php

namespace App\Console\Commands;

use App\Models\FirebaseSyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Database;

/**
 * CleanupFirebaseLegacyNodes (plan section 18.2)
 *
 * Hapus node Firebase legacy yang sudah dimigrasi ke MySQL.
 * IRREVERSIBLE — guard ketat sebelum eksekusi:
 *   1. Feature flag node target HARUS ON
 *   2. Tabel MySQL counterpart TIDAK boleh kosong
 *   3. (waiter_tasks saja) reconcile_run sukses dalam 7 hari terakhir
 *
 * Default --dry-run. Tanpa --confirm, hanya laporan.
 *
 * Usage:
 *   php artisan firebase:cleanup-legacy audit_logs --dry-run
 *   php artisan firebase:cleanup-legacy audit_logs --confirm
 */
class CleanupFirebaseLegacyNodes extends Command
{
    protected $signature = 'firebase:cleanup-legacy
                            {node : Node target (lihat MAP)}
                            {--dry-run : Default. Cek guard, tak hapus.}
                            {--confirm : WAJIB untuk benar-benar hapus.}';

    protected $description = 'Hapus node Firebase legacy setelah MySQL terisi + flag ON (irreversible).';

    /** Map node Firebase -> [feature flag, MySQL table, butuh reconcile guard?] */
    private const MAP = [
        'waiter_tasks'             => ['mysql_waiter_tasks', 'waiter_tasks', true],
        'audit_logs'               => ['mysql_audit_logs', 'audit_logs', false],
        'waiter_activity_reports'  => ['mysql_activity_reports', 'waiter_activity_reports', false],
        'product_categories'       => ['mysql_product_categories', 'product_categories', false],
        'work_shifts'              => ['mysql_work_shifts', 'work_shifts', false],
        'waiter_bonus_summary'     => ['mysql_bonus_summary', 'waiter_bonus_summaries', false],
        'waiter_penalties'         => ['mysql_penalties', 'waiter_penalties', false],
        'waiter_manual_bonuses'    => ['mysql_manual_bonuses', 'waiter_manual_bonuses', false],
        'waiter_attendance'        => ['mysql_attendance', 'waiter_attendances', false],
    ];

    public function handle(Database $database): int
    {
        $node = (string) $this->argument('node');

        if (! isset(self::MAP[$node])) {
            $this->error("Node tidak dikenal: {$node}");
            $this->line('Pilihan: ' . implode(', ', array_keys(self::MAP)));
            return Command::FAILURE;
        }

        [$flagKey, $mysqlTable, $needsReconcileGuard] = self::MAP[$node];

        // GUARD 1 — feature flag ON
        if (! config("features.{$flagKey}")) {
            $this->error("BLOCKED Guard 1: features.{$flagKey} OFF.");
            $this->line('Aktifkan flag dulu, biarkan produksi baca dari MySQL, baru cleanup.');
            return Command::FAILURE;
        }

        // GUARD 2 — MySQL table tidak kosong
        if (! DB::getSchemaBuilder()->hasTable($mysqlTable)) {
            $this->error("BLOCKED Guard 2: tabel {$mysqlTable} tidak ada.");
            return Command::FAILURE;
        }
        $mysqlCount = DB::table($mysqlTable)->count();
        if ($mysqlCount === 0) {
            $this->error("BLOCKED Guard 2: {$mysqlTable} kosong. Seed dulu sebelum cleanup.");
            return Command::FAILURE;
        }

        // GUARD 3 — reconcile sukses 7 hari (waiter_tasks saja, satu-satunya yg punya reconcile)
        if ($needsReconcileGuard) {
            $hasReconcile = FirebaseSyncLog::query()
                ->where('action', 'reconcile')
                ->where('status', 'success')
                ->where('entity_type', 'reconcile_run')
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();
            if (! $hasReconcile) {
                $this->error('BLOCKED Guard 3: tidak ada reconcile_run sukses dalam 7 hari.');
                $this->line('Jalankan php artisan firebase:reconcile-active-tasks dulu, monitor.');
                return Command::FAILURE;
            }
        }

        // Hitung target Firebase
        $fbCount = $database->getReference($node)->getSnapshot()->numChildren();

        $this->info("Guards lulus untuk {$node}.");
        $this->line("  flag={$flagKey}=true | MySQL {$mysqlTable} rows={$mysqlCount} | Firebase node children={$fbCount}");

        if (! $this->option('confirm')) {
            $this->warn('DRY-RUN. Tambahkan --confirm untuk benar-benar hapus.');
            return Command::SUCCESS;
        }

        // Sanity terakhir
        if ($fbCount > $mysqlCount) {
            $this->error("ABORT: Firebase ({$fbCount}) > MySQL ({$mysqlCount}). Re-seed dulu.");
            return Command::FAILURE;
        }

        $this->warn("HAPUS Firebase /{$node} secara permanen ({$fbCount} children)...");
        try {
            $database->getReference($node)->remove();

            FirebaseSyncLog::create([
                'entity_type' => 'cleanup_run',
                'entity_id'   => $node,
                'firebase_path' => $node,
                'action'      => 'remove',
                'status'      => 'success',
                'payload'     => [
                    'firebase_children_removed' => $fbCount,
                    'mysql_rows_at_time'        => $mysqlCount,
                ],
                'attempt_count' => 1,
                'last_attempt_at' => now(),
            ]);

            $this->info("DONE. Node {$node} dihapus dari Firebase. MySQL tetap utuh.");
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            FirebaseSyncLog::create([
                'entity_type' => 'cleanup_run',
                'entity_id'   => $node,
                'firebase_path' => $node,
                'action'      => 'remove',
                'status'      => 'failed',
                'error_message' => $e->getMessage(),
                'attempt_count' => 1,
                'last_attempt_at' => now(),
            ]);
            $this->error('FAILED: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
