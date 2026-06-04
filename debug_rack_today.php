<?php
// Temporary debug script — delete after use
define('LARAVEL_START', microtime(true));

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

// Boot the kernel so all service providers register (incl. config)
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$firebase = $app->make(\App\Services\FirebaseService::class);
$today = date('Y-m-d');

echo "Date: $today\n\n";

$tasks = $firebase->getWaiterTasksByDate($today);

$rackTasks = array_filter($tasks, fn($t) =>
    ($t['task_type'] ?? '') === 'rack_check' &&
    in_array(
        $t['assignment_mode'] ?? $t['assignment_strategy'] ?? '',
        ['simple_lowest_load', 'round_robin_simple'],
        true
    )
);

// Group by waiter
$byWaiter = [];
foreach ($rackTasks as $t) {
    $wid   = (string)($t['assigned_waiter_id'] ?? '?');
    $wname = (string)($t['assigned_waiter_name'] ?? $wid);
    $rack  = (string)($t['rack_name'] ?? $t['rack_id'] ?? '?');
    $rid   = (string)($t['rack_id'] ?? '');
    $status = (string)($t['status'] ?? '?');
    $mode  = (string)($t['assignment_mode'] ?? $t['assignment_strategy'] ?? '?');
    $byWaiter[$wid]['name'] = $wname;
    $byWaiter[$wid]['tasks'][] = [
        'rack'   => $rack,
        'rid'    => $rid,
        'status' => $status,
        'mode'   => $mode,
        'id'     => (string)($t['id'] ?? ''),
    ];
}

// Detect violations
$violations = [];
foreach ($byWaiter as $wid => $data) {
    $count = count($data['tasks']);
    $rids = array_column($data['tasks'], 'rid');
    $dupeRacks = array_filter(array_count_values($rids), fn($c) => $c > 1);
    if (!empty($dupeRacks)) {
        $violations[] = "⚠ RULE1 {$data['name']}: rak duplikat → " . implode(', ', array_map(fn($r) => $r ?: '(no rack_id)', array_keys($dupeRacks)));
    }
    if ($count > 2) {
        $violations[] = "⚠ RULE2 {$data['name']}: {$count} task (melebihi cap 2)";
    }
}

foreach ($byWaiter as $wid => $data) {
    $count = count($data['tasks']);
    $flag = $count > 2 ? ' ❌ OVER CAP' : '';
    echo "── {$data['name']} ({$count} task){$flag} ──\n";
    $rids = array_column($data['tasks'], 'rid');
    $dupRids = array_keys(array_filter(array_count_values($rids), fn($c) => $c > 1));
    foreach ($data['tasks'] as $t) {
        $dupeFlag = ($t['rid'] !== '' && in_array($t['rid'], $dupRids, true)) ? ' ❌ DUPE RAK' : '';
        echo "   [{$t['status']}] {$t['rack']}{$dupeFlag}  (mode:{$t['mode']}) id:{$t['id']}\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total task: " . count($rackTasks) . "\n";
echo "Total waiter: " . count($byWaiter) . "\n";

if (empty($violations)) {
    echo "✓ Tidak ada pelanggaran rule terdeteksi\n";
} else {
    echo "\nPelanggaran:\n";
    foreach ($violations as $v) {
        echo "  $v\n";
    }
}
