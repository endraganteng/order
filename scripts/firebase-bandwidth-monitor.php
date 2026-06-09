#!/usr/bin/env php
<?php

/**
 * Firebase Bandwidth Monitor
 * 
 * Analyzes Firebase RTDB download patterns and estimates daily bandwidth.
 * Run: sudo docker exec order-app php scripts/firebase-bandwidth-monitor.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Kreait\Firebase\Contract\Database;

$db = app(Database::class);

echo "═══════════════════════════════════════════════════════\n";
echo " FIREBASE RTDB BANDWIDTH ANALYSIS - " . date('Y-m-d H:i:s') . "\n";
echo "═══════════════════════════════════════════════════════\n\n";

// 1. Node sizes
echo "📦 NODE SIZES:\n";
echo str_repeat('─', 60) . "\n";

$nodes = [
    'waiter_tasks' => 'Full task history (BIGGEST)',
    'waiter_racks' => 'Rack definitions + products',
    'rack_products' => 'Product-rack mapping',
    'waiter_task_templates' => 'Recurring task templates',
    'waiter_activity_reports' => 'Activity reports',
    'waiter_tasks_active/' . date('Y-m-d') => 'Active tasks today (optimized)',
    'settings' => 'App settings',
    'bonus_config' => 'Bonus configuration',
];

$nodeSizes = [];
foreach ($nodes as $path => $desc) {
    $val = $db->getReference($path)->getValue();
    $sizeBytes = strlen(json_encode($val ?: []));
    $count = is_array($val) ? count($val) : 0;
    $nodeSizes[$path] = $sizeBytes;
    printf("  %-40s %5d entries  %8.1f KB\n", $path, $count, $sizeBytes / 1024);
}
echo "\n";

// 2. waiter_tasks breakdown
echo "🔍 WAITER_TASKS BREAKDOWN:\n";
echo str_repeat('─', 60) . "\n";

$tasks = $db->getReference('waiter_tasks')->getValue() ?: [];
$totalSize = 0;
$photoSize = 0;
$completionSize = 0;
$metaSize = 0;
$byStatus = [];
$byDate = [];

foreach ($tasks as $id => $task) {
    $taskJson = json_encode($task);
    $taskSize = strlen($taskJson);
    $totalSize += $taskSize;
    
    $status = $task['status'] ?? 'unknown';
    $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
    
    $date = $task['scheduled_for_date'] ?? 'no_date';
    $byDate[$date] = ($byDate[$date] ?? 0) + 1;
    
    // Photo fields
    foreach (['completed_photo_proof_url', 'completed_photo_before_url'] as $field) {
        if (isset($task[$field])) {
            $photoSize += strlen(json_encode($task[$field]));
        }
    }
    // Completions field (often contains photos)
    if (isset($task['completions'])) {
        $completionSize += strlen(json_encode($task['completions']));
    }
}

$metaSize = $totalSize - $photoSize - $completionSize;

printf("  Total tasks: %d (%.1f MB)\n", count($tasks), $totalSize / 1024 / 1024);
printf("  Photo data (base64): %.1f MB (%.0f%%)\n", $photoSize / 1024 / 1024, ($photoSize / $totalSize) * 100);
printf("  Completions data: %.1f MB (%.0f%%)\n", $completionSize / 1024 / 1024, ($completionSize / $totalSize) * 100);
printf("  Metadata only: %.1f MB (%.0f%%)\n", $metaSize / 1024 / 1024, ($metaSize / $totalSize) * 100);
echo "\n  Status distribution:\n";
foreach ($byStatus as $s => $c) {
    echo "    $s: $c\n";
}
echo "\n  Date distribution:\n";
krsort($byDate);
foreach ($byDate as $d => $c) {
    echo "    $d: $c tasks\n";
}
echo "\n";

// 3. Scheduler download estimates
echo "📊 ESTIMATED DAILY DOWNLOADS (Scheduler):\n";
echo str_repeat('─', 60) . "\n";

$waiterTasksMB = $totalSize / 1024 / 1024;
$racksMB = ($nodeSizes['waiter_racks'] ?? 0) / 1024 / 1024;

$schedules = [
    ['command' => 'waiter:process-tasks', 'interval' => '5min', 'runs' => 288, 'reads_mb' => $waiterTasksMB + $racksMB],
    ['command' => 'bonus:reconcile-pending', 'interval' => '5min', 'runs' => 288, 'reads_mb' => $waiterTasksMB * 0.5], // partial reads
    ['command' => 'firebase:reconcile-active-tasks', 'interval' => '15min', 'runs' => 96, 'reads_mb' => 0.01], // reads only active node
    ['command' => 'waiter:send-task-reminders', 'interval' => '30min', 'runs' => 48, 'reads_mb' => $waiterTasksMB],
    ['command' => 'firebase:retry-sync', 'interval' => '5min', 'runs' => 288, 'reads_mb' => 0.01], // small reads per task
];

$totalDaily = 0;
foreach ($schedules as $s) {
    $daily = $s['runs'] * $s['reads_mb'];
    $totalDaily += $daily;
    printf("  %-35s %4dx  × %6.1f MB = %7.0f MB/day\n", $s['command'], $s['runs'], $s['reads_mb'], $daily);
}
echo str_repeat('─', 60) . "\n";
printf("  TOTAL SCHEDULER:                                      %7.1f MB/day (%.1f GB)\n", $totalDaily, $totalDaily / 1024);
echo "\n";

// 4. Web requests estimate
echo "🌐 ESTIMATED WEB DOWNLOADS:\n";
echo str_repeat('─', 60) . "\n";
echo "  Waiter portal poll: NOW USES MySQL (0 MB Firebase)\n";
echo "  Live monitor: RTDB listener (persistent, minimal)\n";
echo "  Admin panel views: ~10-50 page loads/day × variable\n";
echo "\n";

// 5. Root cause & recommendations
echo "⚠️  ROOT CAUSE ANALYSIS:\n";
echo str_repeat('─', 60) . "\n";
echo "  1. waiter_tasks node = " . round($waiterTasksMB, 1) . " MB (MASSIVE)\n";
echo "     - Photos stored as base64 INSIDE task nodes\n";
echo "     - Completed tasks from past 7+ days still in node\n";
echo "     - Every scheduler read downloads ALL photos\n";
echo "\n";
echo "  2. Scheduler reads full node every 5 minutes\n";
echo "     - waiter:process-tasks reads entire waiter_tasks\n";
echo "     - bonus:reconcile-pending also reads tasks\n";
echo "     - 288 reads/day × " . round($waiterTasksMB, 1) . " MB = " . round(288 * $waiterTasksMB) . " MB/day EACH\n";
echo "\n";

echo "✅ RECOMMENDATIONS:\n";
echo str_repeat('─', 60) . "\n";
echo "  [HIGH IMPACT] Move photo base64 to separate node or storage:\n";
echo "     - waiter_task_photos/{taskId}/proof_url\n";
echo "     - Or better: upload to GCS/S3, store URL only\n";
echo "     - Saves: ~" . round($photoSize / 1024 / 1024, 1) . " MB per read (" . round(($photoSize / $totalSize) * 100) . "% reduction)\n";
echo "\n";
echo "  [HIGH IMPACT] Cleanup completed tasks from waiter_tasks:\n";
echo "     - Only keep pending/in_progress in waiter_tasks\n";
echo "     - Move completed to waiter_tasks_archive/{date}/\n";
echo "     - Or rely on MySQL (already synced)\n";
echo "\n";
echo "  [MEDIUM] Use targeted queries instead of full node reads:\n";
echo "     - orderByChild('status').equalTo('pending') for scheduler\n";
echo "     - orderByChild('scheduled_for_date').equalTo(today) \n";
echo "\n";
echo "  [MEDIUM] Reduce scheduler frequency:\n";
echo "     - waiter:process-tasks: 5min → 15min (saves 75%)\n";
echo "     - bonus:reconcile-pending: 5min → 15min\n";
echo "\n";

// 6. Quick win calculation
echo "💰 POTENTIAL SAVINGS:\n";
echo str_repeat('─', 60) . "\n";
$currentGB = $totalDaily / 1024;
$afterPhotoRemoval = $totalDaily * ($metaSize / $totalSize) / 1024;
$afterCleanup = $afterPhotoRemoval * 0.3; // only today's tasks remain
printf("  Current: ~%.1f GB/day\n", $currentGB);
printf("  After photo removal: ~%.1f GB/day (-%d%%)\n", $afterPhotoRemoval, round((1 - $afterPhotoRemoval / $currentGB) * 100));
printf("  After cleanup old tasks: ~%.2f GB/day (-%d%%)\n", $afterCleanup, round((1 - $afterCleanup / $currentGB) * 100));
echo "\n";
echo "═══════════════════════════════════════════════════════\n";
