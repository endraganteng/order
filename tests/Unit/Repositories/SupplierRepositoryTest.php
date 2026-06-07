<?php

namespace Tests\Unit\Repositories;

use App\Repositories\SupplierRepository;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Kreait\Firebase\Database\Snapshot;
use PHPUnit\Framework\TestCase;

class SupplierRepositoryTest extends TestCase
{
    public function test_all_returns_sorted_suppliers_with_id(): void
    {
        $snapshot = $this->createMock(Snapshot::class);
        $snapshot->method('exists')->willReturn(true);
        $snapshot->method('getValue')->willReturn([
            'k2' => ['name' => 'Zebra Supply'],
            'k1' => ['name' => 'Alpha Supply'],
        ]);

        $reference = $this->createMock(Reference::class);
        $reference->method('getSnapshot')->willReturn($snapshot);

        $database = $this->createMock(Database::class);
        $database->method('getReference')->with('suppliers')->willReturn($reference);

        $repo = new SupplierRepository($database);
        $result = $repo->all();

        $this->assertCount(2, $result);
        // Sorted by name asc -> Alpha first
        $this->assertSame('Alpha Supply', $result[0]['name']);
        $this->assertSame('k1', $result[0]['id']);
        $this->assertSame('Zebra Supply', $result[1]['name']);
    }

    public function test_all_returns_empty_when_node_missing(): void
    {
        $snapshot = $this->createMock(Snapshot::class);
        $snapshot->method('exists')->willReturn(false);

        $reference = $this->createMock(Reference::class);
        $reference->method('getSnapshot')->willReturn($snapshot);

        $database = $this->createMock(Database::class);
        $database->method('getReference')->willReturn($reference);

        $repo = new SupplierRepository($database);

        $this->assertSame([], $repo->all());
    }

    public function test_create_builds_payload_and_returns_key(): void
    {
        $captured = null;
        $childReference = $this->createMock(Reference::class);
        $childReference->method('getKey')->willReturn('new-supplier-id');

        $reference = $this->createMock(Reference::class);
        $reference->expects($this->once())
            ->method('push')
            ->willReturnCallback(function (array $payload) use (&$captured, $childReference): Reference {
                $captured = $payload;
                return $childReference;
            });

        $database = $this->createMock(Database::class);
        $database->method('getReference')->with('suppliers')->willReturn($reference);

        $repo = new SupplierRepository($database);
        $key = $repo->create(['name' => 'New Co', 'phone' => '0812', 'contact_person' => 'Bob']);

        $this->assertSame('new-supplier-id', $key);
        $this->assertSame('New Co', $captured['name']);
        $this->assertSame('0812', $captured['phone']);
        $this->assertSame('Bob', $captured['contact_person']);
        $this->assertArrayHasKey('created_at', $captured);
        $this->assertArrayHasKey('updated_at', $captured);
    }

    public function test_find_returns_null_when_absent(): void
    {
        $snapshot = $this->createMock(Snapshot::class);
        $snapshot->method('exists')->willReturn(false);

        $reference = $this->createMock(Reference::class);
        $reference->method('getSnapshot')->willReturn($snapshot);

        $database = $this->createMock(Database::class);
        $database->method('getReference')->with('suppliers/missing')->willReturn($reference);

        $repo = new SupplierRepository($database);

        $this->assertNull($repo->find('missing'));
    }
}
