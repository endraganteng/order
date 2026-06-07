<?php

namespace App\Console\Commands;

use App\Models\CashierTask;
use Illuminate\Console\Command;
use Kreait\Firebase\Contract\Database;

/**
 * SeedCashierTasksFromFirebase
 *
 * Baca /cashier_tasks Firebase, tulis ke MySQL. Idempotent via
 * updateOrCreate(deterministic_key) -> aman dijalankan ulang.
 * Mirror payload verbatim ke firebase_payload (read path return ini).
 *
 * Usage:
 *   php artisan seed:cashier-tasks --dry-run
 *   php artisan seed:cashier-tasks
 */
class SeedCashierTasksFromFirebase extends Command
{
    protected $signature = 'seed:cashier-tasks
                            {--dry-run : Hitung saja, tidak tulis MySQL}';

    protected $description = 'Seed cashier_tasks dari Firebase RTDB ke MySQL (idempotent)';

    /** Map status Firebase legacy -> enum MySQL cashier_tasks.status */
    private const STATUS_MAP = [
        'pending' => 'pending',
        'in_progress' => 'in_progress',
        'done' => 'done',
        'completed' => 'done',
        'overdue' => 'overdue',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
        'failed' => 'failed',
    ];

    public function handle(Database $database): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Seed cashier_tasks dari RTDB'.($dryRun ? ' (DRY-RUN)' : ''));

        $snapshot = $database->getReference('cashier_tasks')->getSnapshot();
        $items = $snapshot->getValue() ?: [];

        if (empty($items)) {
            $this->warn('Tidak ada task di /cashier_tasks.');
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

            $deterministicKey = 'ct_legacy_'.substr(hash('sha256', (string) $fbKey), 0, 32);
            $attributes = $this->mapAttributes((string) $fbKey, $task);

            if ($dryRun) {
                CashierTask::where('deterministic_key', $deterministicKey)->exists() ? $updated++ : $created++;
                continue;
            }

            $model = CashierTask::updateOrCreate(
                ['deterministic_key' => $deterministicKey],
                $attributes
            );
            $model->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->info("Selesai. Created: {$created} | Updated: {$updated} | Skipped: {$skipped}");
        return Command::SUCCESS;
    }

    private function mapAttributes(string $fbKey, array $task): array
    {
        $rawStatus = strtolower((string) ($task['status'] ?? 'pending'));
        $status = self::STATUS_MAP[$rawStatus] ?? 'pending';

        $createdAt = $task['created_at'] ?? null;
        $completedAt = $task['completed_at'] ?? null;

        return [
            'firebase_legacy_key' => $fbKey !== '' ? $fbKey : null,
            'source_template_key' => isset($task['source_template_id']) ? (string) $task['source_template_id'] : null,
            'title' => (string) ($task['title'] ?? 'Untitled'),
            'description' => $task['description'] ?? null,
            'scheduled_date' => $task['scheduled_for_date'] ?? now()->format('Y-m-d'),
            'scheduled_time' => $task['scheduled_time'] ?? null,
            'status' => $status,
            'is_recurring' => (bool) ($task['is_recurring_instance'] ?? false),
            'recurrence_pattern' => $task['recurrence_type'] ?? null,
            'notes' => $task['completed_note'] ?? null,
            'completed_at' => is_numeric($completedAt) ? date('Y-m-d H:i:s', (int) $completedAt) : null,
            'created_at' => is_numeric($createdAt) ? date('Y-m-d H:i:s', (int) $createdAt) : null,
            'firebase_payload' => $task,
        ];
    }
}
