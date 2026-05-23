<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use Illuminate\Console\Command;

/**
 * BonusRackRecheckMarkLegacy
 *
 * Tandai task `rack_check` yang sudah `done` tapi tidak punya field
 * `recheck_pending` (legacy data sebelum schema migration) sebagai pending
 * review — supaya muncul di antrian Finance dan bisa direview.
 *
 * Tanpa command ini, task lama silently terjebak: UI Finance filter
 * `! empty(recheck_pending)`, jadi task tanpa field itu tidak pernah masuk
 * antrian.
 *
 * Idempotent. Aman dijalankan ulang.
 *
 * Usage:
 *   php artisan bonus:rack-mark-legacy --dry-run
 *   php artisan bonus:rack-mark-legacy
 *   php artisan bonus:rack-mark-legacy --from=2026-05-01
 */
class BonusRackRecheckMarkLegacy extends Command
{
    protected $signature = 'bonus:rack-mark-legacy
                            {--dry-run : Cetak rencana, tidak menulis}
                            {--from= : Hanya tandai task dengan scheduled_for_date >= YYYY-MM-DD}
                            {--to= : Hanya tandai task dengan scheduled_for_date <= YYYY-MM-DD}';

    protected $description = 'Tandai task rack_check legacy (done tanpa recheck_pending) sebagai pending review.';

    public function handle(FirebaseService $firebase): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $from = $this->validateDate($this->option('from'));
        $to = $this->validateDate($this->option('to'));

        $this->info('=== Mark Legacy rack_check as recheck_pending ===');
        $this->line($dryRun ? 'Mode: DRY RUN' : 'Mode: APPLY');
        if ($from) {
            $this->line('From: ' . $from);
        }
        if ($to) {
            $this->line('To  : ' . $to);
        }
        $this->newLine();

        $tasks = $firebase->getWaiterTasks();
        $candidates = [];
        foreach ($tasks as $t) {
            if (($t['task_type'] ?? '') !== 'rack_check') {
                continue;
            }
            if (($t['status'] ?? '') !== 'done') {
                continue;
            }
            // SKIP yang sudah ada recheck_pending (true/false), itu schema baru.
            if (array_key_exists('recheck_pending', $t)) {
                continue;
            }
            $date = (string) ($t['scheduled_for_date'] ?? '');
            if ($from && $date < $from) {
                continue;
            }
            if ($to && $date > $to) {
                continue;
            }
            $candidates[] = $t;
        }

        $this->info('Found ' . count($candidates) . ' legacy task(s) yang perlu di-mark.');

        if (empty($candidates)) {
            return self::SUCCESS;
        }

        $rows = [];
        $applied = 0;
        $errors = 0;

        foreach ($candidates as $t) {
            $id = (string) ($t['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $rows[] = [
                'id' => $id,
                'date' => $t['scheduled_for_date'] ?? '?',
                'rack' => $t['rack_name'] ?? ($t['title'] ?? '?'),
                'completed_by' => $t['completed_by_waiter_name'] ?? '?',
                'action' => $dryRun ? 'WOULD-MARK' : 'MARK',
            ];

            if (! $dryRun) {
                $result = $firebase->markRackCheckPendingReview($id);
                if ($result['success'] ?? false) {
                    $applied++;
                } else {
                    $errors++;
                    $rows[count($rows) - 1]['action'] = 'ERROR: ' . ($result['message'] ?? 'unknown');
                }
            }
        }

        $this->table(
            ['ID', 'Date', 'Rack', 'Completed By', 'Action'],
            array_map(fn ($r) => array_values($r), $rows)
        );

        $this->newLine();
        $this->info('=== Summary ===');
        $this->line('Marked  : ' . $applied);
        $this->line('Errors  : ' . $errors);
        if ($dryRun) {
            $this->warn('DRY RUN: tidak ada perubahan. Hapus --dry-run untuk apply.');
        } else {
            $this->info('Selesai. Buka UI Finance "Recheck Pending" — task harusnya muncul.');
            $this->info('Setelah Finance review, jalankan: php artisan bonus:rack-recheck-backfill');
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
            $this->warn('Tanggal tidak valid (harus YYYY-MM-DD): ' . $value);

            return null;
        }

        return $value;
    }
}
