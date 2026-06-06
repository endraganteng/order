<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ProductCategory;
use App\Models\WaiterActivityReport;
use App\Models\WaiterAttendance;
use App\Models\WaiterBonusSummary;
use App\Models\WaiterManualBonus;
use App\Models\WaiterPenalty;
use App\Models\WorkShift;
use Illuminate\Console\Command;
use Kreait\Firebase\Contract\Database;

/**
 * SeedFirebaseNodes
 *
 * Seed legacy Firebase data into MySQL for the phase 2/3 migrated nodes.
 * Idempotent (updateOrCreate by firebase_legacy_key / natural key).
 * Run per node BEFORE enabling that node's feature flag, so historical
 * data is present when reads switch to MySQL.
 *
 * Usage:
 *   php artisan seed:firebase-nodes audit_logs --dry-run
 *   php artisan seed:firebase-nodes product_categories
 *   php artisan seed:firebase-nodes attendance --from=2026-05-01 --to=2026-06-06
 */
class SeedFirebaseNodes extends Command
{
    protected $signature = 'seed:firebase-nodes
                            {node : audit_logs|activity_reports|product_categories|work_shifts|bonus_summary|penalties|manual_bonuses|attendance}
                            {--dry-run : Hitung saja}
                            {--from= : Mulai YYYY-MM-DD (untuk node bertanggal)}
                            {--to= : Akhir YYYY-MM-DD}';

    protected $description = 'Seed legacy Firebase nodes ke MySQL (idempotent, per node)';

    public function handle(Database $db): int
    {
        $node = (string) $this->argument('node');
        $dry = (bool) $this->option('dry-run');

        $method = 'seed' . str_replace(' ', '', ucwords(str_replace('_', ' ', $node)));
        if (! method_exists($this, $method)) {
            $this->error("Node tidak dikenal: {$node}");
            return Command::FAILURE;
        }

        $this->info("Seed {$node}" . ($dry ? ' (DRY-RUN)' : ''));
        [$scanned, $written] = $this->{$method}($db, $dry);
        $this->info("Scanned: {$scanned} | Written: {$written}");
        return Command::SUCCESS;
    }

    private function range(): array
    {
        return [
            $this->option('from') ?: now()->subDays(90)->format('Y-m-d'),
            $this->option('to') ?: now()->format('Y-m-d'),
        ];
    }

    private function seedProductCategories(Database $db, bool $dry): array
    {
        $items = $db->getReference('product_categories')->getSnapshot()->getValue() ?: [];
        $written = 0;
        foreach ($items as $key => $row) {
            if (! is_array($row)) continue;
            if (! $dry) {
                ProductCategory::updateOrCreate(['firebase_legacy_key' => (string) $key], [
                    'name' => (string) ($row['name'] ?? ''),
                    'description' => $row['description'] ?? null,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'event_created_at' => $row['created_at'] ?? null,
                    'event_updated_at' => $row['updated_at'] ?? null,
                ]);
            }
            $written++;
        }
        return [count($items), $written];
    }

    private function seedWorkShifts(Database $db, bool $dry): array
    {
        $items = $db->getReference('work_shifts')->getSnapshot()->getValue() ?: [];
        $written = 0;
        foreach ($items as $key => $row) {
            if (! is_array($row)) continue;
            if (! $dry) {
                WorkShift::updateOrCreate(['firebase_legacy_key' => (string) $key], [
                    'name' => (string) ($row['name'] ?? ''),
                    'clock_in_time' => $row['clock_in_time'] ?? null,
                    'clock_out_time' => $row['clock_out_time'] ?? null,
                    'late_tolerance_minutes' => (int) ($row['late_tolerance_minutes'] ?? 0),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'retail_tag' => $row['retail_tag'] ?? null,
                    'event_created_at' => $row['created_at'] ?? null,
                    'event_updated_at' => $row['updated_at'] ?? null,
                ]);
            }
            $written++;
        }
        return [count($items), $written];
    }

    private function seedAuditLogs(Database $db, bool $dry): array
    {
        $items = $db->getReference('audit_logs')->getSnapshot()->getValue() ?: [];
        $written = 0;
        foreach ($items as $key => $row) {
            if (! is_array($row)) continue;
            if (! $dry) {
                AuditLog::updateOrCreate(['firebase_legacy_key' => (string) $key], [
                    'action' => (string) ($row['action'] ?? ''),
                    'entity' => (string) ($row['entity'] ?? ''),
                    'entity_id' => $row['entity_id'] ?? null,
                    'admin_id' => $row['admin_id'] ?? null,
                    'admin_name' => $row['admin_name'] ?? null,
                    'details' => $row['details'] ?? null,
                    'ip' => $row['ip'] ?? null,
                    'event_timestamp' => (int) ($row['timestamp'] ?? 0),
                    'event_date' => $row['date'] ?? now()->format('Y-m-d'),
                ]);
            }
            $written++;
        }
        return [count($items), $written];
    }

    private function seedActivityReports(Database $db, bool $dry): array
    {
        $items = $db->getReference('waiter_activity_reports')->getSnapshot()->getValue() ?: [];
        $written = 0;
        foreach ($items as $key => $row) {
            if (! is_array($row)) continue;
            if (! $dry) {
                WaiterActivityReport::updateOrCreate(['firebase_legacy_key' => (string) $key], [
                    'waiter_id' => (string) ($row['waiter_id'] ?? ''),
                    'waiter_name' => $row['waiter_name'] ?? null,
                    'waiter_email' => $row['waiter_email'] ?? null,
                    'report_date' => $row['report_date'] ?? now()->format('Y-m-d'),
                    'activity_text' => $row['activity_text'] ?? null,
                    'activity_items' => $row['activity_items'] ?? null,
                    'event_timestamp' => $row['created_at'] ?? null,
                ]);
            }
            $written++;
        }
        return [count($items), $written];
    }

    private function seedPenalties(Database $db, bool $dry): array
    {
        [$from, $to] = $this->range();
        $items = $db->getReference('waiter_penalties')
            ->orderByChild('date')->startAt($from)->endAt($to)
            ->getSnapshot()->getValue() ?: [];
        $written = 0;
        foreach ($items as $key => $row) {
            if (! is_array($row)) continue;
            if (! $dry) {
                WaiterPenalty::updateOrCreate(['firebase_legacy_key' => (string) $key], [
                    'waiter_id' => (string) ($row['waiter_id'] ?? ''),
                    'waiter_name' => $row['waiter_name'] ?? null,
                    'penalty_type' => $row['penalty_type'] ?? null,
                    'penalty_label' => $row['penalty_label'] ?? null,
                    'points_deducted' => (int) ($row['points_deducted'] ?? 0),
                    'date' => $row['date'] ?? $from,
                    'month' => $row['month'] ?? null,
                    'reason' => $row['reason'] ?? null,
                    'evidence_photo_url' => $row['evidence_photo_url'] ?? null,
                    'related_task_id' => $row['related_task_id'] ?? null,
                    'event_created_at' => $row['created_at'] ?? null,
                ]);
            }
            $written++;
        }
        return [count($items), $written];
    }

    private function seedManualBonuses(Database $db, bool $dry): array
    {
        [$from, $to] = $this->range();
        $items = $db->getReference('waiter_manual_bonuses')
            ->orderByChild('date')->startAt($from)->endAt($to)
            ->getSnapshot()->getValue() ?: [];
        $written = 0;
        foreach ($items as $key => $row) {
            if (! is_array($row)) continue;
            if (! $dry) {
                WaiterManualBonus::updateOrCreate(['firebase_legacy_key' => (string) $key], [
                    'waiter_id' => (string) ($row['waiter_id'] ?? ''),
                    'waiter_name' => $row['waiter_name'] ?? null,
                    'month' => $row['month'] ?? null,
                    'date' => $row['date'] ?? $from,
                    'points' => (int) ($row['points'] ?? 0),
                    'reason' => $row['reason'] ?? null,
                    'category' => $row['category'] ?? null,
                    'created_by' => $row['created_by'] ?? null,
                    'event_created_at' => $row['created_at'] ?? null,
                ]);
            }
            $written++;
        }
        return [count($items), $written];
    }

    private function seedBonusSummary(Database $db, bool $dry): array
    {
        // Node keyed: waiter_bonus_summary/{waiterId}/{periodKey}
        $byWaiter = $db->getReference('waiter_bonus_summary')->getSnapshot()->getValue() ?: [];
        $scanned = 0;
        $written = 0;
        foreach ($byWaiter as $waiterId => $periods) {
            if (! is_array($periods)) continue;
            foreach ($periods as $periodKey => $summary) {
                if (! is_array($summary)) continue;
                $scanned++;
                if (! $dry) {
                    WaiterBonusSummary::updateOrCreate(
                        ['waiter_id' => (string) $waiterId, 'period_key' => (string) $periodKey],
                        [
                            'status' => $summary['status'] ?? 'finalized',
                            'finalized_at' => $summary['finalized_at'] ?? null,
                            'summary' => $summary,
                        ]
                    );
                }
                $written++;
            }
        }
        return [$scanned, $written];
    }

    private function seedAttendance(Database $db, bool $dry): array
    {
        // Node keyed: waiter_attendance/{waiterId}/{date}
        $byWaiter = $db->getReference('waiter_attendance')->getSnapshot()->getValue() ?: [];
        $scanned = 0;
        $written = 0;
        foreach ($byWaiter as $waiterId => $dates) {
            if (! is_array($dates)) continue;
            foreach ($dates as $date => $record) {
                if (! is_array($record)) continue;
                $scanned++;
                if (! $dry) {
                    WaiterAttendance::updateOrCreate(
                        ['waiter_id' => (string) $waiterId, 'date' => (string) $date],
                        [
                            'status' => $record['status'] ?? null,
                            'late_minutes' => (int) ($record['late_minutes'] ?? 0),
                            'clock_in' => $record['clock_in'] ?? null,
                            'clock_out' => $record['clock_out'] ?? null,
                            'data' => $record,
                        ]
                    );
                }
                $written++;
            }
        }
        return [$scanned, $written];
    }
}
