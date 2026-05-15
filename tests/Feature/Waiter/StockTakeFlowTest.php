<?php

namespace Tests\Feature\Waiter;

use App\Services\BonusService;
use App\Services\FirebaseService;
use App\Services\FonnteService;
use Mockery;
use Tests\TestCase;

class StockTakeFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_complete_task_creates_restock_request_for_storage_shortage(): void
    {
        $today = date('Y-m-d');
        $capturedRestockPayload = null;

        $firebase = Mockery::mock(FirebaseService::class);
        $bonus = Mockery::mock(BonusService::class);
        $fonnte = Mockery::mock(FonnteService::class);

        $firebase->shouldReceive('updateWaiterTaskStatus')
            ->once()
            ->withArgs(function (...$args) {
                return $args[0] === 'task-1'
                    && $args[1] === 'done'
                    && $args[2] === 'waiter-1'
                    && $args[3] === 'Waiter Satu'
                    && $args[4] === 'waiter@example.com'
                    && $args[5] === 'Cek stok rak'
                    && $args[6] === 'RACK-001'
                    && $args[7] === 'Susu UHT, Teh Botol'
                    && $args[8] === false
                    && $args[9] === null
                    && is_array($args[10])
                    && ($args[10]['prod-1']['actual_qty'] ?? null) === 1
                    && $args[11] === null;
            })
            ->andReturn([
                'success' => true,
                'partial' => false,
                'completed_count' => 1,
                'repeat_count' => 1,
                'message' => 'Tugas berhasil diverifikasi.',
            ]);

        $firebase->shouldReceive('getWaiterTaskById')->once()->with('task-1')->andReturn([
            'id' => 'task-1',
            'rack_id' => 'rack-storage',
            'rack_name' => 'Rak Gudang',
            'title' => 'Cek Rak Gudang',
        ]);
        $firebase->shouldReceive('getRackById')->once()->with('rack-storage')->andReturn([
            'id' => 'rack-storage',
            'rack_type' => 'storage',
        ]);
        $firebase->shouldReceive('getProductCategoriesMap')->once()->andReturn([
            'cat-1' => ['name' => 'Minuman'],
        ]);
        $firebase->shouldReceive('getProductById')->once()->with('prod-1')->andReturn([
            'id' => 'prod-1',
            'category_id' => 'cat-1',
        ]);
        $firebase->shouldReceive('createOrUpdateRestockRequest')
            ->andReturnUsing(function (array $data) use (&$capturedRestockPayload) {
                $capturedRestockPayload = $data;

                return 'restock-1';
            });
        $firebase->shouldReceive('getAttendanceByDate')->once()->andReturn(null);
        $firebase->shouldReceive('getWaiterTasksForDate')->once()->andReturn([]);
        $firebase->shouldReceive('getWaiterActivityReportsByWaiterIdForDate')->once()->andReturn([]);

        $bonus->shouldReceive('autoScoreDailyPoints')->once()->andReturn([
            'discipline' => 10,
            'operational' => 10,
            'attitude' => 10,
            'auto_details' => [],
        ]);
        $bonus->shouldReceive('saveAutoDailyScore')->once();

        $this->instance(FirebaseService::class, $firebase);
        $this->instance(BonusService::class, $bonus);
        $this->instance(FonnteService::class, $fonnte);

        $response = $this->withSession([
            'waiter_authenticated' => true,
            'waiter_id' => 'waiter-1',
            'waiter_name' => 'Waiter Satu',
            'waiter_email' => 'waiter@example.com',
        ])->postJson(route('waiter.task.complete', ['id' => 'task-1']), [
            'note' => 'Cek stok rak',
            'scanned_barcode' => 'RACK-001',
            'stock_report_items' => 'Susu UHT, Teh Botol',
            'no_out_of_stock' => false,
            'product_checklist' => json_encode([
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
            ]),
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('partial', false);
        $response->assertJsonPath('completed_count', 1);
        $response->assertJsonPath('repeat_count', 1);

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
    }

    public function test_complete_task_does_not_create_restock_request_for_display_rack_without_refill_shortage(): void
    {
        $firebase = Mockery::mock(FirebaseService::class);
        $bonus = Mockery::mock(BonusService::class);
        $fonnte = Mockery::mock(FonnteService::class);

        $firebase->shouldReceive('updateWaiterTaskStatus')
            ->once()
            ->andReturn([
                'success' => true,
                'partial' => false,
                'completed_count' => 1,
                'repeat_count' => 1,
                'message' => 'Tugas berhasil diverifikasi.',
            ]);
        $firebase->shouldReceive('getWaiterTaskById')->once()->andReturn([
            'id' => 'task-2',
            'rack_id' => 'rack-display',
            'rack_name' => 'Rak Display',
            'title' => 'Cek Rak Display',
        ]);
        $firebase->shouldReceive('getRackById')->once()->with('rack-display')->andReturn([
            'id' => 'rack-display',
            'rack_type' => 'display',
        ]);
        $firebase->shouldReceive('getProductCategoriesMap')->once()->andReturn([]);
        $firebase->shouldNotReceive('getProductById');
        $firebase->shouldNotReceive('createOrUpdateRestockRequest');
        $firebase->shouldReceive('getAttendanceByDate')->once()->andReturn(null);
        $firebase->shouldReceive('getWaiterTasksForDate')->once()->andReturn([]);
        $firebase->shouldReceive('getWaiterActivityReportsByWaiterIdForDate')->once()->andReturn([]);

        $bonus->shouldReceive('autoScoreDailyPoints')->once()->andReturn([
            'discipline' => 10,
            'operational' => 10,
            'attitude' => 10,
            'auto_details' => [],
        ]);
        $bonus->shouldReceive('saveAutoDailyScore')->once();

        $this->instance(FirebaseService::class, $firebase);
        $this->instance(BonusService::class, $bonus);
        $this->instance(FonnteService::class, $fonnte);

        $response = $this->withSession([
            'waiter_authenticated' => true,
            'waiter_id' => 'waiter-1',
            'waiter_name' => 'Waiter Satu',
            'waiter_email' => 'waiter@example.com',
        ])->postJson(route('waiter.task.complete', ['id' => 'task-2']), [
            'note' => 'Cek display',
            'scanned_barcode' => 'RACK-002',
            'stock_report_items' => '',
            'no_out_of_stock' => true,
            'product_checklist' => json_encode([
                'prod-3' => [
                    'checked' => true,
                    'actual_qty' => 1,
                    'standard_qty' => 4,
                    'was_refilled' => false,
                    'product_name' => 'Kopi Sachet',
                    'product_unit' => 'pcs',
                ],
            ]),
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }
}
