<?php

namespace Tests\Unit\Repositories;

use App\Repositories\CashierTaskRepository;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Kreait\Firebase\Database\Snapshot;
use Tests\TestCase;

/**
 * Characterization tests for CashierTaskRepository RTDB-path behavior.
 * Uses Laravel TestCase so config() resolves; flag forced off to exercise the
 * RTDB fallback + sorting + recurring-map logic. Database is mocked (no Firebase).
 */
class CashierTaskRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['features.mysql_cashier_tasks' => false]);
    }

    private function databaseReturning(array $nodeValue, bool $exists = true): Database
    {
        $snapshot = $this->createMock(Snapshot::class);
        $snapshot->method('exists')->willReturn($exists);
        $snapshot->method('getValue')->willReturn($nodeValue);

        $reference = $this->createMock(Reference::class);
        $reference->method('getSnapshot')->willReturn($snapshot);
        $reference->method('orderByChild')->willReturnSelf();
        $reference->method('equalTo')->willReturnSelf();

        $database = $this->createMock(Database::class);
        $database->method('getReference')->willReturn($reference);

        return $database;
    }

    public function test_all_sorts_by_created_at_desc_rtdb(): void
    {
        $db = $this->databaseReturning([
            'a' => ['title' => 'Old', 'created_at' => 100],
            'b' => ['title' => 'New', 'created_at' => 200],
        ]);

        $repo = new CashierTaskRepository($db);
        $result = $repo->all();

        $this->assertCount(2, $result);
        $this->assertSame('New', $result[0]['title']); // newest first
        $this->assertSame('a', $result[1]['id']);
    }

    public function test_all_active_filters_pending_only(): void
    {
        $db = $this->databaseReturning([
            'a' => ['title' => 'P', 'status' => 'pending', 'created_at' => 100],
            'b' => ['title' => 'D', 'status' => 'done', 'created_at' => 200],
        ]);

        $repo = new CashierTaskRepository($db);
        $active = $repo->allActive();

        $this->assertCount(1, $active);
        $this->assertSame('P', array_values($active)[0]['title']);
    }

    public function test_existing_recurring_map_rtdb_marks_pending_template_instances(): void
    {
        $db = $this->databaseReturning([
            'a' => ['source_template_id' => 't1', 'scheduled_for_date' => '2026-06-07', 'status' => 'pending'],
            'b' => ['source_template_id' => 't2', 'scheduled_for_date' => '2026-06-07', 'status' => 'done'],
            'c' => ['source_template_id' => 't3', 'scheduled_for_date' => '2026-06-06', 'status' => 'pending'],
        ]);

        $repo = new CashierTaskRepository($db);
        $map = $repo->existingRecurringMap('2026-06-07');

        $this->assertArrayHasKey('t1', $map);   // pending + matching date
        $this->assertArrayNotHasKey('t2', $map); // done -> excluded
        $this->assertArrayNotHasKey('t3', $map); // wrong date -> excluded
    }
}
