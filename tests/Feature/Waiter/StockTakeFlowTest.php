<?php

namespace Tests\Feature\Waiter;

use App\Services\FirebaseService;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Restock auto-collection moved from WaiterController into
 * FirebaseService::updateWaiterTaskStatus -> writeRestockRequestsForCompletion
 * (P0-3 atomicity: shortage signal persisted before task status flips to done).
 *
 * These tests exercise that protected helper directly so the real shortage
 * decision logic is covered without seeding the full RTDB task-completion path.
 */
class StockTakeFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @return array{0: FirebaseService, 1: ReflectionMethod}
     */
    private function makeServiceWithStubs(): array
    {
        $database = Mockery::mock(Database::class);
        $auth = Mockery::mock(Auth::class);

        $service = Mockery::mock(FirebaseService::class, [$database, $auth])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $method = new ReflectionMethod(FirebaseService::class, 'writeRestockRequestsForCompletion');
        $method->setAccessible(true);

        return [$service, $method];
    }

    public function test_storage_rack_shortage_creates_restock_request(): void
    {
        $today = date('Y-m-d');
        $capturedRestockPayload = null;

        [$service, $invoke] = $this->makeServiceWithStubs();

        $service->shouldReceive('getRackById')->once()->with('rack-storage')->andReturn([
            'id' => 'rack-storage',
            'rack_type' => 'storage',
        ]);
        $service->shouldReceive('getProductCategoriesMap')->once()->andReturn([
            'cat-1' => ['name' => 'Minuman'],
        ]);
        $service->shouldReceive('getProductById')->once()->with('prod-1')->andReturn([
            'id' => 'prod-1',
            'category_id' => 'cat-1',
        ]);
        $service->shouldReceive('createOrUpdateRestockRequest')
            ->once()
            ->andReturnUsing(function (array $data) use (&$capturedRestockPayload) {
                $capturedRestockPayload = $data;

                return 'restock-1';
            });

        $task = [
            'id' => 'task-1',
            'rack_id' => 'rack-storage',
            'rack_name' => 'Rak Gudang',
            'title' => 'Cek Rak Gudang',
        ];

        $productChecklist = [
            'prod-1' => [
                'product_id' => 'prod-1',
                'checked' => true,
                'actual_qty' => 1,
                'standard_qty' => 4,
                'min_qty' => 2,
                'product_name' => 'Susu UHT',
                'product_unit' => 'pcs',
            ],
            'prod-2' => [
                'product_id' => 'prod-2',
                'checked' => true,
                'actual_qty' => 4,
                'standard_qty' => 4,
                'min_qty' => 1,
                'product_name' => 'Teh Botol',
                'product_unit' => 'pcs',
            ],
        ];

        $result = $invoke->invoke($service, 'task-1', $task, $productChecklist, 'waiter-1', 'Waiter Satu');

        $this->assertTrue($result['success']);
        $this->assertIsArray($capturedRestockPayload);
        $this->assertSame('prod-1', $capturedRestockPayload['product_id']);
        $this->assertSame('Susu UHT', $capturedRestockPayload['product_name']);
        $this->assertSame('Minuman', $capturedRestockPayload['product_category_name']);
        $this->assertSame('rack-storage', $capturedRestockPayload['rack_id']);
        $this->assertSame('Rak Gudang', $capturedRestockPayload['rack_name']);
        $this->assertSame(1, $capturedRestockPayload['reported_qty']);
        $this->assertSame(4, $capturedRestockPayload['standard_qty']);
        $this->assertSame(2, $capturedRestockPayload['min_qty']);
        $this->assertSame(3, $capturedRestockPayload['qty_needed']);
        $this->assertSame('waiter-1', $capturedRestockPayload['reported_by']);
        $this->assertSame('Waiter Satu', $capturedRestockPayload['reported_by_name']);
        $this->assertSame($today, $capturedRestockPayload['date']);
        $this->assertSame('storage_rack_shortage', $capturedRestockPayload['source']);
    }

    public function test_display_rack_shortage_covered_by_storage_does_not_create_restock(): void
    {
        [$service, $invoke] = $this->makeServiceWithStubs();

        $service->shouldReceive('getRackById')->once()->with('rack-display')->andReturn([
            'id' => 'rack-display',
            'rack_type' => 'display',
        ]);
        $service->shouldReceive('getProductCategoriesMap')->once()->andReturn([]);
        // Display short by 3, but storage holds 5 -> combined (1+5) >= standard (4),
        // waiter can refill from storage, so no supervisor restock escalation.
        $service->shouldReceive('getTotalStorageQtyForProduct')->once()->with('prod-3')->andReturn(5);
        $service->shouldNotReceive('getProductById');
        $service->shouldNotReceive('createOrUpdateRestockRequest');

        $task = [
            'id' => 'task-2',
            'rack_id' => 'rack-display',
            'rack_name' => 'Rak Display',
            'title' => 'Cek Rak Display',
        ];

        $productChecklist = [
            'prod-3' => [
                'product_id' => 'prod-3',
                'checked' => true,
                'actual_qty' => 1,
                'standard_qty' => 4,
                'was_refilled' => false,
                'product_name' => 'Kopi Sachet',
                'product_unit' => 'pcs',
            ],
        ];

        $result = $invoke->invoke($service, 'task-2', $task, $productChecklist, 'waiter-1', 'Waiter Satu');

        $this->assertTrue($result['success']);
    }
}
