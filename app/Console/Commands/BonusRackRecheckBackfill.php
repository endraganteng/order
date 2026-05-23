<?php

namespace App\Console\Commands;

use App\Services\BonusService;
use App\Services\FirebaseService;
use Illuminate\Console\Command;

/**
 * BonusRackRecheckBackfill
 *
 * One-shot rescue tool: scan task `rack_check` yang sudah direview Finance
 * (status=done, recheck_pending falsy, recheck_points ada), lalu re-trigger
 * `BonusService::mergeRackRecheckPoints` per (waiter, tanggal) pair.
 *
 * Diperlukan untuk back-fill poin yang silently di-skip oleh 3 bug:
 *  1. admin_override=true memblokir saveAutoDailyScore
 *  2. recheck_pending="false" (string Firebase) lolos filter empty()
 *  3. assigned_waiter_id null pada task role-based, controller skip auto-rescore
 *
 * Idempotent: aman dijalankan ulang. Score di-recompute dari rata-rata
 * recheck_points × (reviewed/total) setiap kali.
 *
 * Usage:
 *   php artisan bonus:rack-recheck-backfill --dry-run
 *   php artisan bonus:rack-recheck-backfill --from=2026-05-01 --to=2026-05-23
 *   php artisan bonus:rack-recheck-backfill --waiter=W123
 */
class BonusRackRecheckBackfill extends Command
{
    protected $signature = 'bonus:rack-recheck-backfill
                            {--dry-run : Hanya cetak rencana, tidak menulis ke Firebase}
                            {--from= : Filter scheduled_for_date >= YYYY-MM-DD}
                            {--to= : Filter scheduled_for_date <= YYYY-MM-DD}
                            {--waiter= : Hanya proses waiter ID tertentu}';

    protected $description = 'Backfill rack_recheck points untuk task yang sudah direview Finance tapi poinnya tidak masuk.';

    public function handle(FirebaseService $firebase, BonusService $bonus): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $from = $this->validateDate($this->option('from'));
        $to = $this->validateDate($this->option('to'));
        $waiterFilter = trim((string) ($this->option('waiter') ?? ''));

        $this->info('=== Rack Recheck Backfill ===');
        $this->line($dryRun ? 'Mode: DRY RUN (tidak menulis)' : 'Mode: APPLY (menulis ke Firebase)');
        if ($from) {
            $this->line('From: ' . $from);
        }
        if ($to) {
            $this->line('To  : ' . $to);
        }
        if ($waiterFilter !== '') {
            $this->line('Waiter: ' . $waiterFilter);
        }
        $this->newLine();

        $this->line('Loading semua waiter_tasks dari Firebase...');
        $allTasks = $firebase->getWaiterTasks();
        $this->info('Loaded ' . count($allTasks) . ' tasks total.');

        // Filter: rack_check + done + sudah direview (recheck_points ada & recheck_pending falsy)
        $reviewed = [];
        foreach ($allTasks as $task) {
            if (($task['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if (($task['status'] ?? '') !== 'done') {
                continue;
            }
            if (! isset($task['recheck_points'])) {
                continue;
            }
            if (! $this->isReviewed($task['recheck_pending'] ?? null)) {
                continue;
            }

            $date = (string) ($task['scheduled_for_date'] ?? '');
            if ($date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                continue;
            }
            if ($from !== null && $date < $from) {
                continue;
            }
            if ($to !== null && $date > $to) {
                continue;
            }

            $waiterId = $this->resolveWaiterId($task);
            if ($waiterId === '') {
                continue;
            }
            if ($waiterFilter !== '' && $waiterId !== $waiterFilter) {
                continue;
            }

            $reviewed[] = $task + ['_resolved_waiter_id' => $waiterId, '_resolved_date' => $date];
        }

        $this->info('Filtered ' . count($reviewed) . ' reviewed rack_check tasks dalam range.');

        if (empty($reviewed)) {
            $this->warn('Tidak ada task yang perlu di-backfill.');

            return self::SUCCESS;
        }

        // Group per (waiter, date) — autoScoreDailyPoints butuh SEMUA rack tasks hari itu.
        $pairs = [];
        foreach ($reviewed as $t) {
            $key = $t['_resolved_waiter_id'] . '|' . $t['_resolved_date'];
            $pairs[$key] = true;
        }

        $this->info('Pairs (waiter,date) yang akan di-rescore: ' . count($pairs));
        $this->newLine();

        $applied = 0;
        $skipped = 0;
        $errors = 0;
        $rows = [];

        foreach (array_keys($pairs) as $key) {
            [$waiterId, $date] = explode('|', $key, 2);
            try {
                $allWaiterTasks = $firebase->getWaiterTasksForDate($waiterId, $date);
                $scores = $bonus->autoScoreDailyPoints($waiterId, $date, null, $allWaiterTasks, []);
                $rackScore = (int) ($scores['rack_recheck'] ?? 0);

                $existing = $bonus->getDailyPoints($waiterId, $date);
                $existingRack = (int) ($existing['categories']['rack_recheck'] ?? 0);
                $adminOverride = (bool) ($existing['admin_override'] ?? false);
                $hasRecord = $existing !== null;

                $needsUpdate = $existingRack !== $rackScore;

                $rows[] = [
                    'waiter' => $waiterId,
                    'date' => $date,
                    'has_record' => $hasRecord ? 'yes' : 'no',
                    'override' => $adminOverride ? 'yes' : '-',
                    'old_rack' => $existingRack,
                    'new_rack' => $rackScore,
                    'action' => $needsUpdate ? ($dryRun ? 'WOULD-WRITE' : 'WRITE') : 'skip',
                ];

                if (! $needsUpdate) {
                    $skipped++;
                    continue;
                }

                if (! $dryRun) {
                    $bonus->mergeRackRecheckPoints(
                        $waiterId,
                        $date,
                        $rackScore,
                        'Backfill rack_recheck via artisan',
                        [
                            'rack_recheck_reason' => 'Backfilled by bonus:rack-recheck-backfill',
                        ]
                    );
                }

                $applied++;
            } catch (\Throwable $e) {
                $errors++;
                $rows[] = [
                    'waiter' => $waiterId,
                    'date' => $date,
                    'has_record' => '?',
                    'override' => '?',
                    'old_rack' => '?',
                    'new_rack' => '?',
                    'action' => 'ERROR: ' . $e->getMessage(),
                ];
                report($e);
            }
        }

        $this->table(
            ['Waiter', 'Date', 'Has Record', 'Override', 'Old', 'New', 'Action'],
            array_map(fn ($r) => array_values($r), $rows)
        );

        $this->info('=== Summary ===');
        $this->line('Applied : ' . $applied);
        $this->line('Skipped : ' . $skipped . ' (sudah sesuai)');
        $this->line('Errors  : ' . $errors);
        if ($dryRun) {
            $this->warn('DRY RUN: tidak ada perubahan yang ditulis. Hapus --dry-run untuk apply.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function validateDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $this->warn('Tanggal tidak valid (harus YYYY-MM-DD): ' . $value . ' — diabaikan.');

            return null;
        }

        return $value;
    }

    /**
     * Mirror logic dari BonusService::autoScoreDailyPoints (post-fix):
     * task dianggap reviewed kalau recheck_pending bukan true (boolean atau string).
     */
    private function isReviewed(mixed $pending): bool
    {
        if ($pending === null || $pending === false || $pending === 0 || $pending === '0' || $pending === '') {
            return true;
        }
        $bool = filter_var($pending, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $bool === false;
    }

    /**
     * Mirror controller fix: assigned_waiter_id → completed_by_waiter_id → claimed_by.
     */
    private function resolveWaiterId(array $task): string
    {
        $candidates = [
            $task['assigned_waiter_id'] ?? '',
            $task['completed_by_waiter_id'] ?? '',
            $task['claimed_by'] ?? '',
        ];
        foreach ($candidates as $c) {
            $c = trim((string) $c);
            if ($c !== '') {
                return $c;
            }
        }

        return '';
    }
}
