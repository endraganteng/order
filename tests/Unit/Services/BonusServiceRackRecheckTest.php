<?php

namespace Tests\Unit\Services;

use App\Services\BonusService;
use App\Services\FirebaseService;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Kreait\Firebase\Database\Snapshot;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests untuk bug "Finance sudah review tapi point tidak masuk".
 *
 * 3 bug yang difix:
 *  #1  saveAutoDailyScore di-skip diam-diam saat admin_override=true.
 *      Fix: rute Finance recheck pakai mergeRackRecheckPoints (targeted update,
 *      tidak menyentuh admin_override).
 *  #2  Filter `empty($task['recheck_pending'])` lolos kalau Firebase mengembalikan
 *      string "false" (non-empty/truthy). Fix: parser eksplisit pakai filter_var
 *      FILTER_VALIDATE_BOOLEAN di autoScoreDailyPoints.
 *  #3  WaiterController::submitRackCheckReview hanya membaca assigned_waiter_id;
 *      task role-based punya field itu null → auto-rescore di-skip. (Tidak
 *      direproduksi di test ini, butuh mock controller; dicek lewat code review.)
 */
class BonusServiceRackRecheckTest extends TestCase
{
    private function buildServiceWithStore(array &$store): BonusService
    {
        $database = $this->createMock(Database::class);

        $database->method('getReference')->willReturnCallback(function (string $path) use (&$store) {
            return $this->makeReference($path, $store);
        });

        return new class($this->createMock(FirebaseService::class), $database) extends BonusService
        {
            public function getBonusConfig(): array
            {
                return $this->getDefaultConfig();
            }
        };
    }

    private function makeReference(string $path, array &$store): Reference
    {
        $reference = $this->createMock(Reference::class);

        $reference->method('getSnapshot')->willReturnCallback(function () use ($path, &$store) {
            $value = $store[$path] ?? null;
            $snapshot = $this->createMock(Snapshot::class);
            $snapshot->method('exists')->willReturn($value !== null);
            $snapshot->method('getValue')->willReturn($value);

            return $snapshot;
        });

        $reference->method('set')->willReturnCallback(function ($value) use ($path, &$store, $reference) {
            $store[$path] = $value;

            return $reference;
        });

        return $reference;
    }

    public function test_baseline_score_with_rack_recheck_writes_record(): void
    {
        $store = [];
        $service = $this->buildServiceWithStore($store);

        $service->saveAutoDailyScore('w1', '2026-05-23', [
            'discipline'   => 5,
            'operational'  => 10,
            'attitude'     => 5,
            'rack_recheck' => 8,
        ], 'auto', []);

        $record = $store['waiter_daily_points/w1/2026-05-23'] ?? null;
        $this->assertNotNull($record);
        $this->assertSame(8, $record['categories']['rack_recheck']);
        $this->assertSame(28, $record['raw_total']);
    }

    /**
     * Regression #1: Setelah supervisor save manual (admin_override=true), Finance
     * review HARUS tetap masuk via mergeRackRecheckPoints. Skor manual lain
     * (discipline/operational/attitude) tidak boleh berubah, admin_override flag
     * tetap dipertahankan.
     */
    public function test_merge_rack_recheck_updates_score_even_with_admin_override(): void
    {
        $store = [];
        $service = $this->buildServiceWithStore($store);

        $service->saveAdminDailyScore('w1', '2026-05-23', [
            'discipline'  => 4,
            'operational' => 7,
            'attitude'    => 3,
        ], 'manual admin entry');

        $afterAdmin = $store['waiter_daily_points/w1/2026-05-23'];
        $this->assertTrue($afterAdmin['admin_override']);
        $this->assertSame(0, $afterAdmin['categories']['rack_recheck'] ?? -1);

        $result = $service->mergeRackRecheckPoints('w1', '2026-05-23', 8, 'Auto-rescore on Finance recheck', [
            'rack_recheck_reason' => '1/1 rak direview Finance, total 8 poin',
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['merged']);

        $after = $store['waiter_daily_points/w1/2026-05-23'];

        $this->assertSame(8, $after['categories']['rack_recheck']);
        $this->assertSame(4, $after['categories']['discipline']);
        $this->assertSame(7, $after['categories']['operational']);
        $this->assertSame(3, $after['categories']['attitude']);
        $this->assertTrue($after['admin_override']);
        $this->assertSame(22, $after['raw_total']);
    }

    public function test_merge_rack_recheck_clamps_to_category_max(): void
    {
        $store = [];
        $service = $this->buildServiceWithStore($store);

        $result = $service->mergeRackRecheckPoints('w1', '2026-05-23', 99, '', []);

        $this->assertTrue($result['success']);
        $this->assertSame(10, $store['waiter_daily_points/w1/2026-05-23']['categories']['rack_recheck']);
    }

    /**
     * Regression #2: filter recheck_pending tidak boleh tertipu string "false".
     */
    public function test_autoscore_handles_string_false_recheck_pending(): void
    {
        $service = new BonusService(
            $this->createMock(FirebaseService::class),
            $this->createMock(Database::class)
        );

        $tasks = [
            [
                'id' => 't1',
                'task_type' => 'rack_check',
                'status' => 'done',
                'assigned_waiter_id' => 'w1',
                'recheck_pending' => 'false',
                'recheck_points' => 9,
            ],
        ];

        $scores = $service->autoScoreDailyPoints('w1', '2026-05-23', null, $tasks, []);

        $this->assertSame(9, $scores['rack_recheck']);
    }

    public function test_autoscore_handles_boolean_false_recheck_pending(): void
    {
        $service = new BonusService(
            $this->createMock(FirebaseService::class),
            $this->createMock(Database::class)
        );

        $tasks = [
            ['id'=>'t1','task_type'=>'rack_check','status'=>'done','assigned_waiter_id'=>'w1','recheck_pending'=>false,'recheck_points'=>7],
        ];

        $scores = $service->autoScoreDailyPoints('w1', '2026-05-23', null, $tasks, []);
        $this->assertSame(7, $scores['rack_recheck']);
    }

    public function test_autoscore_skips_pending_rack_check(): void
    {
        $service = new BonusService(
            $this->createMock(FirebaseService::class),
            $this->createMock(Database::class)
        );

        $tasks = [
            ['id'=>'t1','task_type'=>'rack_check','status'=>'done','assigned_waiter_id'=>'w1','recheck_pending'=>true],
            ['id'=>'t2','task_type'=>'rack_check','status'=>'done','assigned_waiter_id'=>'w1','recheck_pending'=>'true'],
        ];

        $scores = $service->autoScoreDailyPoints('w1', '2026-05-23', null, $tasks, []);
        $this->assertSame(0, $scores['rack_recheck']);
    }

    public function test_autoscore_skips_task_without_recheck_points(): void
    {
        $service = new BonusService(
            $this->createMock(FirebaseService::class),
            $this->createMock(Database::class)
        );

        $tasks = [
            ['id'=>'t1','task_type'=>'rack_check','status'=>'done','assigned_waiter_id'=>'w1','recheck_pending'=>false],
        ];

        $scores = $service->autoScoreDailyPoints('w1', '2026-05-23', null, $tasks, []);
        $this->assertSame(0, $scores['rack_recheck']);
    }

    public function test_autoscore_pro_rates_partial_reviews(): void
    {
        $service = new BonusService(
            $this->createMock(FirebaseService::class),
            $this->createMock(Database::class)
        );

        $tasks = [
            ['id'=>'r1','task_type'=>'rack_check','status'=>'done','assigned_waiter_id'=>'w1','recheck_pending'=>false,'recheck_points'=>8],
            ['id'=>'r2','task_type'=>'rack_check','status'=>'done','assigned_waiter_id'=>'w1','recheck_pending'=>true],
            ['id'=>'r3','task_type'=>'rack_check','status'=>'done','assigned_waiter_id'=>'w1','recheck_pending'=>true],
            ['id'=>'r4','task_type'=>'rack_check','status'=>'done','assigned_waiter_id'=>'w1','recheck_pending'=>true],
            ['id'=>'r5','task_type'=>'rack_check','status'=>'done','assigned_waiter_id'=>'w1','recheck_pending'=>true],
        ];

        $scores = $service->autoScoreDailyPoints('w1', '2026-05-23', null, $tasks, []);
        $this->assertSame(2, $scores['rack_recheck']);
    }
}
