<?php

namespace Tests\Unit\Repositories;

use App\Repositories\RackRepository;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Kreait\Firebase\Database\Snapshot;
use PHPUnit\Framework\TestCase;

/**
 * Characterization tests for RackRepository (no config() dependency, pure
 * RTDB + request cache + check_order sort). Mocked Database.
 */
class RackRepositoryTest extends TestCase
{
    private function databaseReturning(array $nodeValue, bool $exists = true): Database
    {
        $snapshot = $this->createMock(Snapshot::class);
        $snapshot->method('exists')->willReturn($exists);
        $snapshot->method('getValue')->willReturn($nodeValue);

        $reference = $this->createMock(Reference::class);
        $reference->method('getSnapshot')->willReturn($snapshot);

        $database = $this->createMock(Database::class);
        $database->method('getReference')->willReturn($reference);

        return $database;
    }

    public function test_all_sorts_by_check_order_then_name(): void
    {
        $db = $this->databaseReturning([
            'a' => ['name' => 'Bravo', 'check_order' => 2],
            'b' => ['name' => 'Alpha', 'check_order' => 1],
            'c' => ['name' => 'Charlie', 'check_order' => 1],
        ]);

        $repo = new RackRepository($db);
        $result = $repo->all();

        // check_order 1 group first; within it, name asc -> Alpha then Charlie
        $this->assertSame('Alpha', $result[0]['name']);
        $this->assertSame('Charlie', $result[1]['name']);
        $this->assertSame('Bravo', $result[2]['name']);
    }

    public function test_all_active_excludes_inactive(): void
    {
        $db = $this->databaseReturning([
            'a' => ['name' => 'Active', 'is_active' => true, 'check_order' => 1],
            'b' => ['name' => 'Inactive', 'is_active' => false, 'check_order' => 2],
        ]);

        $repo = new RackRepository($db);
        $active = $repo->allActive();

        $this->assertCount(1, $active);
        $this->assertSame('Active', $active[0]['name']);
    }

    public function test_find_returns_rack_by_id(): void
    {
        $db = $this->databaseReturning([
            'rack-1' => ['name' => 'R1', 'check_order' => 1],
            'rack-2' => ['name' => 'R2', 'check_order' => 2],
        ]);

        $repo = new RackRepository($db);
        $found = $repo->find('rack-2');

        $this->assertNotNull($found);
        $this->assertSame('R2', $found['name']);
        $this->assertNull($repo->find('nope'));
    }

    public function test_all_caches_within_instance(): void
    {
        $snapshot = $this->createMock(Snapshot::class);
        $snapshot->method('exists')->willReturn(true);
        $snapshot->method('getValue')->willReturn(['a' => ['name' => 'X', 'check_order' => 1]]);

        $reference = $this->createMock(Reference::class);
        // getSnapshot must be called only ONCE despite two all() calls (cache)
        $reference->expects($this->once())->method('getSnapshot')->willReturn($snapshot);

        $database = $this->createMock(Database::class);
        $database->method('getReference')->willReturn($reference);

        $repo = new RackRepository($database);
        $repo->all();
        $repo->all();

        $this->assertTrue(true); // expectation verified by mock
    }
}
