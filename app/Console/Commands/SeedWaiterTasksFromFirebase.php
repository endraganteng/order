<?php

namespace App\Console\Commands;

use App\Models\WaiterTask;
use Illuminate\Console\Command;
use Kreait\Firebase\Contract\Database;

/**
 * SeedWaiterTasksFromFirebase (plan 11.2 langkah 1 + 9.2)
 *
 * Baca /waiter_tasks Firebase pada rentang tanggal, tulis ke MySQL.
 * Idempotent via updateOrCreate(deterministic_key) -> aman dijalankan ulang.
 *
 * Status enum legacy Firebase dipetakan ke enum MySQL (revisi 3).
 *
 * Usage:
 *   php artisan seed:waiter-tasks --dry-run
 *   php artisan seed:waiter-tasks --from=2026-05-01 --to=2026-06-06
 */
class SeedWaiterTasksFromFirebase extends Command
{
    protected $signature = 'seed:waiter-tasks
                            {--dry-run : Hitung saja, tidak tulis MySQL}
                            {--from= : Mulai YYYY-MM-DD (default 30 hari lalu)}
                            {--to= : Akhir YYYY-MM-DD (default hari ini)}';

    protected $description = 'Seed waiter_tasks dari Firebase RTDB ke MySQL (idempotent)';

    /** Map status Firebase legacy -> enum MySQL waiter_tasks.status */
    private const STATUS_MAP = [
        'pending' => 'pending',
        'in_progress' => 'in_progress',
        'done' => 'done',
        'completed' => 'done',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
        'rescheduled' => 'rescheduled',
        'ignored' => 'ignored',
        'failed' => 'failed',
    ];

    public function handle(Database $database): int
    {
        $from = $this->option('from') ?: now()->subDays(30)->format('Y-m-d');
        $to = $this->option('to') ?: now()->format('Y-m-d');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Seed waiter_tasks [{$from}..{$to}]".($dryRun ? ' (DRY-RUN)' : ''));

        $snapshot = $database->getReference('waiter_tasks')
            ->orderByChild('scheduled_for_date')
            ->startAt($from)
            ->endAt($to)
            ->getSnapshot();

        $items = $snapshot->getValue() ?: [];
        if (empty($items)) {
            $this->warn('Tidak ada task pada rentang ini.');
            return Command::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $fbKey => $task) {
            if (! is_array($task)) {
                $skipped++;
                continue;
            }

            $waiterId = (string) ($task['assigned_waiter_id'] ?? '');
            $scheduledDate = (string) ($task['scheduled_for_date'] ?? '');
            if ($waiterId === '' || $scheduledDate === '') {
                $skipped++;
                continue;
            }

            $deterministicKey = $this->deterministicKey($fbKey, $task, $waiterId, $scheduledDate);
            $attributes = $this->mapAttributes($fbKey, $task, $waiterId, $scheduledDate);

            if ($dryRun) {
                WaiterTask::where('deterministic_key', $deterministicKey)->exists() ? $updated++ : $created++;
                continue;
            }

            $model = WaiterTask::updateOrCreate(
                ['deterministic_key' => $deterministicKey],
                $attributes
            );
            $model->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->info("Selesai. Created: {$created} | Updated: {$updated} | Skipped: {$skipped}");
        return Command::SUCCESS;
    }

    /**
     * Deterministic key (plan 9.2). firebase_legacy_key dipakai sebagai dasar
     * agar re-seed stabil; fallback ke hash komposit bila key kosong.
     */
    private function deterministicKey(string $fbKey, array $task, string $waiterId, string $date): string
    {
        if ($fbKey !== '') {
            return 'wt_legacy_'.substr(hash('sha256', $fbKey), 0, 32);
        }

        return 'wt_'.substr(hash('sha256', implode('::', [
            $task['source_template_id'] ?? 'none',
            $waiterId,
            $date,
            $task['task_type'] ?? 'general',
            $task['rack_id'] ?? 'none',
        ])), 0, 32);
    }

    private function mapAttributes(string $fbKey, array $task, string $waiterId, string $date): array
    {
        $rawStatus = strtolower((string) ($task['status'] ?? 'pending'));
        $status = self::STATUS_MAP[$rawStatus] ?? 'pending';

        $createdAt = $task['created_at'] ?? null;
        $createdAtTs = is_numeric($createdAt) ? date('Y-m-d H:i:s', (int) $createdAt) : null;

        return [
            'firebase_legacy_key' => $fbKey !== '' ? $fbKey : null,
            'task_type' => in_array(($task['task_type'] ?? 'general'), ['general', 'rack_check'], true)
                ? $task['task_type'] : 'general',
            'title' => (string) ($task['title'] ?? 'Untitled'),
            'description' => $task['description'] ?? null,
            'assigned_waiter_id' => $waiterId,
            'assigned_waiter_name' => $task['assigned_waiter_name'] ?? null,
            'scheduled_for_date' => $date,
            'status' => $status,
            'publish_status' => 'draft',
            'sync_status' => 'pending',
            'priority' => in_array(($task['priority'] ?? 'normal'), ['low', 'normal', 'high', 'urgent'], true)
                ? $task['priority'] : 'normal',
            'rack_id' => $task['rack_id'] ?? null,
            'rack_name' => $task['rack_name'] ?? null,
            'completed_at' => is_numeric($task['completed_at'] ?? null)
                ? date('Y-m-d H:i:s', (int) $task['completed_at']) : null,
            'notes' => $task['completed_note'] ?? null,
            'created_by' => $task['assigned_by'] ?? null,
            'created_at' => $createdAtTs,
        ];
    }
}
