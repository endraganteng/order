<?php

namespace Tests\Unit\Services;

use App\Services\FirebaseService;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Snapshot;
use Kreait\Firebase\Database\Reference;
use PHPUnit\Framework\TestCase;

class FirebaseServiceTest extends TestCase
{
    public function test_update_attendance_derives_timestamps_from_time_strings(): void
    {
        $capturedPayload = null;

        $database = $this->createMock(Database::class);
        $reference = $this->createMock(Reference::class);
        $reference->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $payload) use (&$capturedPayload, $reference): Reference {
                $capturedPayload = $payload;
                return $reference;
            });
        $database->method('getReference')->willReturn($reference);

        $service = new FirebaseService($database, $this->createMock(Auth::class));
        $service->updateAttendance('waiter-1', '2026-05-04', [
            'clock_in' => '08:30',
            'clock_out' => '17:45',
            'status' => 'present',
        ]);

        $this->assertIsArray($capturedPayload);
        $this->assertSame('08:30', $capturedPayload['clock_in']);
        $this->assertSame('17:45', $capturedPayload['clock_out']);
        $this->assertSame(strtotime('2026-05-04 08:30:00'), $capturedPayload['clock_in_timestamp']);
        $this->assertSame(strtotime('2026-05-04 17:45:00'), $capturedPayload['clock_out_timestamp']);
        $this->assertSame('admin_override', $capturedPayload['method']);
    }

    public function test_get_cashier_attendance_qr_data_stores_qr_state_separately_from_attendance_record(): void
    {
        $today = date('Y-m-d');
        [$database, $store] = $this->makeDatabaseWithState([
            'allowed_waiters' => [
                'waiter-1' => [
                    'id' => 'waiter-1',
                    'name' => 'Waiter Satu',
                    'is_active' => true,
                    'attendance_exempt' => false,
                ],
            ],
            'settings' => ['clock_out_enabled' => true],
        ]);

        $service = new FirebaseService($database, $this->createMock(Auth::class));
        $payload = $service->getCashierAttendanceQrData('waiter-1');

        $this->assertTrue($payload['found']);
        $this->assertTrue($payload['available']);
        $this->assertSame('clock_in', $payload['purpose']);
        $this->assertNotSame('', $payload['qr_value']);
        $this->assertArrayHasKey('waiter_attendance_qr/waiter-1/'.$today, $store->data);
        $this->assertArrayNotHasKey('waiter_attendance/waiter-1/'.$today, $store->data);
    }

    public function test_process_attendance_qr_scan_rotates_tokens_and_requires_new_token_for_clock_out(): void
    {
        $today = date('Y-m-d');
        $todayName = strtolower(date('l', strtotime($today)));
        [$database, $store] = $this->makeDatabaseWithState([
            'allowed_waiters' => [
                'waiter-1' => [
                    'id' => 'waiter-1',
                    'name' => 'Waiter Satu',
                    'is_active' => true,
                    'attendance_exempt' => false,
                ],
            ],
            'waiter_schedule_template' => [
                'waiter-1' => [
                    $todayName => 'shift-1',
                ],
            ],
            'work_shifts/shift-1' => [
                'id' => 'shift-1',
                'name' => 'Shift Pagi',
                'clock_in_time' => '00:00',
                'clock_out_time' => '23:59',
                'late_tolerance_minutes' => 0,
                'is_active' => true,
            ],
            'settings' => ['clock_out_enabled' => true],
        ]);

        $service = new FirebaseService($database, $this->createMock(Auth::class));

        $clockInPayload = $service->getCashierAttendanceQrData('waiter-1');
        $clockInToken = $clockInPayload['qr_value'];

        $clockInResult = $service->processAttendanceQrScan('waiter-1', 'clock_in', $clockInToken, 'qr_scan');
        $this->assertTrue($clockInResult['success']);
        $this->assertArrayHasKey('waiter_attendance/waiter-1/'.$today, $store->data);
        $this->assertSame('qr_scan', $store->data['waiter_attendance/waiter-1/'.$today]['method']);
        $this->assertNotEmpty($store->data['waiter_attendance/waiter-1/'.$today]['clock_in']);

        $clockOutPayload = $service->getCashierAttendanceQrData('waiter-1');
        $this->assertSame('clock_out', $clockOutPayload['purpose']);
        $this->assertNotSame($clockInToken, $clockOutPayload['qr_value']);

        $invalidClockOut = $service->processAttendanceQrScan('waiter-1', 'clock_out', $clockInToken, 'qr_scan');
        $this->assertFalse($invalidClockOut['success']);
        $this->assertSame('QR code absensi tidak valid', $invalidClockOut['message']);

        $clockOutResult = $service->processAttendanceQrScan('waiter-1', 'clock_out', $clockOutPayload['qr_value'], 'qr_scan');
        $this->assertTrue($clockOutResult['success']);
        $this->assertNotEmpty($store->data['waiter_attendance/waiter-1/'.$today]['clock_out']);
        $this->assertArrayHasKey('waiter_attendance_qr/waiter-1/'.$today, $store->data);
        $this->assertSame(1, $store->data['waiter_attendance_qr/waiter-1/'.$today]['clock_in']['use_count']);
        $this->assertSame(1, $store->data['waiter_attendance_qr/waiter-1/'.$today]['clock_out']['use_count']);
    }

    public function test_update_waiter_task_status_records_stock_report_checklist_and_scan_attempt(): void
    {
        $today = date('Y-m-d');
        [$database, $store] = $this->makeDatabaseWithState([
            'allowed_waiters' => [
                'waiter-1' => [
                    'id' => 'waiter-1',
                    'name' => 'Waiter Satu',
                    'is_active' => true,
                ],
            ],
            'waiter_tasks/task-1' => [
                'assigned_waiter_id' => 'waiter-1',
                'assigned_waiter_name' => 'Waiter Satu',
                'status' => 'pending',
                'task_type' => 'rack_check',
                'rack_id' => 'rack-storage',
                'rack_name' => 'Rak Gudang',
                'rack_barcode_value' => 'RACK-001',
                'completed_count' => 0,
                'repeat_count' => 1,
            ],
            'waiter_racks' => [
                'rack-storage' => [
                    'id' => 'rack-storage',
                    'barcode_value' => 'RACK-001',
                    'rack_type' => 'storage',
                    'products' => [
                        'prod-1' => [
                            'product_id' => 'prod-1',
                            'standard_qty' => 4,
                            'min_qty' => 1,
                            'current_qty' => 3,
                            'assigned_at' => time(),
                            'updated_at' => time(),
                        ],
                        'prod-2' => [
                            'product_id' => 'prod-2',
                            'standard_qty' => 4,
                            'min_qty' => 0,
                            'current_qty' => 4,
                            'assigned_at' => time(),
                            'updated_at' => time(),
                        ],
                    ],
                ],
            ],
            'rack_stock_movements' => [],
        ]);

        $service = new FirebaseService($database, $this->createMock(Auth::class));
        $result = $service->updateWaiterTaskStatus(
            'task-1',
            'done',
            'waiter-1',
            'Waiter Satu',
            'waiter@example.com',
            'Cek stok rak',
            'RACK-001',
            'Susu UHT, Susu UHT, Teh Botol',
            false,
            null,
            [
                'prod-1' => [
                    'checked' => true,
                    'actual_qty' => 1,
                    'standard_qty' => 4,
                    'product_name' => 'Susu UHT',
                    'product_unit' => 'pcs',
                ],
                'prod-2' => [
                    'checked' => true,
                    'actual_qty' => 4,
                    'standard_qty' => 4,
                    'product_name' => 'Teh Botol',
                    'product_unit' => 'pcs',
                ],
            ],
            null
        );

        $this->assertTrue($result['success']);
        $this->assertSame('Tugas berhasil diverifikasi.', $result['message']);

        $task = $store->data['waiter_tasks/task-1'];
        $this->assertSame(1, $task['completed_count']);
        $this->assertSame('done', $task['status']);
        $this->assertSame('Cek stok rak', $task['completed_note']);
        $this->assertSame('RACK-001', $task['completed_scanned_barcode']);
        $this->assertSame('Susu UHT, Susu UHT, Teh Botol', $task['completed_stock_report']);
        $this->assertSame(['Susu UHT', 'Teh Botol'], $task['completed_stock_report_items']);
        $this->assertFalse($task['completed_no_out_of_stock']);
        $this->assertTrue($task['completed_product_checklist']['prod-1']['is_shortage']);
        $this->assertFalse($task['completed_product_checklist']['prod-2']['is_shortage']);

        $rack = $store->data['waiter_racks']['rack-storage'];
        $this->assertSame(1, $rack['products']['prod-1']['current_qty']);
        $this->assertSame('stock_take', $rack['products']['prod-1']['last_movement_type']);
        $this->assertCount(2, $store->data['rack_stock_movements']);

        $scanStats = $store->data['scan_attempts/waiter-1/'.$today];
        $this->assertSame(1, $scanStats['total']);
        $this->assertSame(0, $scanStats['mismatch']);
        $this->assertTrue($scanStats['logs'][0]['success']);
        $this->assertSame('rack-storage', $scanStats['logs'][0]['rack_id']);
    }

    public function test_receive_po_item_increments_received_qty_and_updates_restock_request(): void
    {
        [$database, $store] = $this->makeDatabaseWithState([
            'purchase_orders/po-1' => [
                'status' => 'ordered',
                'items' => [
                    'restock-1' => [
                        'qty_ordered' => 5,
                        'received_qty' => 2,
                        'received' => false,
                        'rack_id' => 'rack-storage',
                        'product_id' => 'prod-1',
                        'product_name' => 'Susu UHT',
                        'product_unit' => 'pcs',
                    ],
                    'restock-2' => [
                        'qty_ordered' => 3,
                        'received_qty' => 0,
                        'received' => false,
                    ],
                ],
            ],
            'restock_requests/restock-1' => [
                'qty_needed' => 5,
                'status' => 'ordered',
                'received_qty' => 2,
            ],
            'waiter_racks' => [
                'rack-storage' => [
                    'id' => 'rack-storage',
                    'rack_type' => 'storage',
                    'products' => [
                        'prod-1' => [
                            'product_id' => 'prod-1',
                            'standard_qty' => 5,
                            'min_qty' => 1,
                            'current_qty' => 3,
                            'assigned_at' => time(),
                            'updated_at' => time(),
                        ],
                    ],
                ],
            ],
            'rack_stock_movements' => [],
            'po_receive_idempotency' => [],
        ]);

        $service = new FirebaseService($database, $this->createMock(Auth::class));
        $result = $service->receivePoItem('po-1', 'restock-1', 3, 'waiter-1', 'Waiter Satu');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['item_completed']);
        $this->assertSame(5, $result['new_received_qty']);
        $this->assertSame(5, $result['qty_ordered']);
        $this->assertSame('partial', $result['po_status']);
        $this->assertSame(1, $result['received_count']);

        $po = $store->data['purchase_orders/po-1'];
        $this->assertSame(5, $po['items']['restock-1']['received_qty']);
        $this->assertTrue($po['items']['restock-1']['received']);
        $this->assertSame('partial', $po['status']);
        $this->assertSame(1, $po['received_count']);

        $restock = $store->data['restock_requests/restock-1'];
        $this->assertSame(5, $restock['received_qty']);
        $this->assertSame('received', $restock['status']);

        $rack = $store->data['waiter_racks']['rack-storage'];
        $this->assertSame(6, $rack['products']['prod-1']['current_qty']);
        $this->assertSame('po_receive', $rack['products']['prod-1']['last_movement_type']);
        $this->assertCount(1, $store->data['rack_stock_movements']);

    }

    public function test_receive_po_item_idempotency_key_prevents_double_counting(): void
    {
        [$database, $store] = $this->makeDatabaseWithState([
            'purchase_orders/po-1' => [
                'status' => 'ordered',
                'items' => [
                    'restock-1' => [
                        'qty_ordered' => 5,
                        'received_qty' => 2,
                        'received' => false,
                        'rack_id' => 'rack-storage',
                        'product_id' => 'prod-1',
                        'product_name' => 'Susu UHT',
                        'product_unit' => 'pcs',
                    ],
                ],
            ],
            'restock_requests/restock-1' => [
                'qty_needed' => 5,
                'status' => 'ordered',
                'received_qty' => 2,
            ],
            'waiter_racks' => [
                'rack-storage' => [
                    'id' => 'rack-storage',
                    'rack_type' => 'storage',
                    'products' => [
                        'prod-1' => [
                            'product_id' => 'prod-1',
                            'standard_qty' => 5,
                            'min_qty' => 1,
                            'current_qty' => 3,
                            'assigned_at' => time(),
                            'updated_at' => time(),
                        ],
                    ],
                ],
            ],
            'rack_stock_movements' => [],
            'po_receive_idempotency' => [],
        ]);

        $service = new FirebaseService($database, $this->createMock(Auth::class));

        $first = $service->receivePoItem('po-1', 'restock-1', 2, 'waiter-1', 'Waiter Satu', 'idem-123');
        $second = $service->receivePoItem('po-1', 'restock-1', 2, 'waiter-1', 'Waiter Satu', 'idem-123');

        $this->assertTrue($first['success']);
        $this->assertSame($first, $second);
        $this->assertSame(5, $store->data['waiter_racks']['rack-storage']['products']['prod-1']['current_qty']);
        $this->assertCount(1, $store->data['rack_stock_movements']);
    }

    /**
     * @return array{0: Database, 1: object}
     */
    private function makeDatabaseWithState(array $initialState): array
    {
        $store = (object) ['data' => $initialState];
        $references = [];
        $referencePaths = [];
        $pushCounters = [];

        $database = $this->createMock(Database::class);
        $database->method('getReference')->willReturnCallback(function (string $path) use ($store, &$references, &$referencePaths, &$pushCounters): Reference {
            if (isset($references[$path])) {
                return $references[$path];
            }

            $reference = $this->createMock(Reference::class);
            $referencePaths[spl_object_id($reference)] = $path;

            $reference->method('getSnapshot')->willReturnCallback(function () use ($store, $path, $reference): Snapshot {
                return new Snapshot($reference, $this->readStoreValue($store, $path));
            });

            $reference->method('set')->willReturnCallback(function ($value) use ($store, $path, $reference): Reference {
                $this->writeStoreValue($store, $path, $value);
                return $reference;
            });

            $reference->method('push')->willReturnCallback(function ($value = null) use ($store, $path, $reference, &$references, &$referencePaths, &$pushCounters): Reference {
                $pushCounters[$path] = ($pushCounters[$path] ?? 0) + 1;
                $childKey = 'push_'.$pushCounters[$path];
                $childPath = $path.'/'.$childKey;

                $childReference = $this->createMock(Reference::class);
                $referencePaths[spl_object_id($childReference)] = $childPath;

                $childReference->method('getSnapshot')->willReturnCallback(function () use ($store, $childPath, $childReference): Snapshot {
                    return new Snapshot($childReference, $this->readStoreValue($store, $childPath));
                });

                $childReference->method('set')->willReturnCallback(function ($childValue) use ($store, $childPath, $childReference): Reference {
                    $this->writeStoreValue($store, $childPath, $childValue);
                    return $childReference;
                });

                $childReference->method('update')->willReturnCallback(function (array $payload) use ($store, $childPath, $childReference): Reference {
                    $current = $this->readStoreValue($store, $childPath) ?? [];
                    if (! is_array($current)) {
                        $current = [];
                    }

                    foreach ($payload as $updatePath => $updateValue) {
                        $this->setNestedArrayValue($current, explode('/', (string) $updatePath), $updateValue);
                    }

                    $this->writeStoreValue($store, $childPath, $current);
                    return $childReference;
                });

                $childReference->method('getKey')->willReturn($childKey);

                if ($value !== null) {
                    $this->writeStoreValue($store, $childPath, $value);
                }

                $references[$childPath] = $childReference;

                return $childReference;
            });

            $reference->method('update')->willReturnCallback(function (array $payload) use ($store, $path, $reference): Reference {
                $current = $this->readStoreValue($store, $path) ?? [];
                if (! is_array($current)) {
                    $current = [];
                }

                foreach ($payload as $updatePath => $updateValue) {
                    $this->setNestedArrayValue($current, explode('/', (string) $updatePath), $updateValue);
                }

                $this->writeStoreValue($store, $path, $current);

                return $reference;
            });

            $reference->method('getKey')->willReturn(basename($path));

            $references[$path] = $reference;

            return $reference;
        });

        $database->method('runTransaction')->willReturnCallback(function (callable $callback) use ($store, &$referencePaths) {
            $transaction = new class($store, $referencePaths, $this) {
                public function __construct(private object $store, private array $referencePaths, private FirebaseServiceTest $test)
                {
                }

                public function snapshot(Reference $reference): Snapshot
                {
                    $path = $this->referencePaths[spl_object_id($reference)] ?? '';
                    return new Snapshot($reference, $this->test->readStoreValue($this->store, $path));
                }

                public function set(Reference $reference, mixed $value): void
                {
                    $path = $this->referencePaths[spl_object_id($reference)] ?? null;
                    if ($path === null) {
                        return;
                    }

                    $this->test->writeStoreValue($this->store, $path, $value);
                }
            };

            return $callback($transaction);
        });

        return [$database, $store];
    }

    public function readStoreValue(object $store, string $path): mixed
    {
        if (array_key_exists($path, $store->data)) {
            return $store->data[$path];
        }

        foreach ($store->data as $candidatePath => $candidateValue) {
            if (! is_array($candidateValue) || ! str_starts_with($path, $candidatePath.'/')) {
                continue;
            }

            $segments = explode('/', substr($path, strlen($candidatePath) + 1));
            $current = $candidateValue;
            foreach ($segments as $segment) {
                if (! is_array($current) || ! array_key_exists($segment, $current)) {
                    return null;
                }
                $current = $current[$segment];
            }

            return $current;
        }

        return null;
    }

    public function writeStoreValue(object $store, string $path, mixed $value): void
    {
        $ancestorPath = null;
        foreach (array_keys($store->data) as $candidatePath) {
            if ($candidatePath === $path || ! str_starts_with($path, $candidatePath.'/')) {
                continue;
            }

            if ($ancestorPath === null || strlen($candidatePath) > strlen($ancestorPath)) {
                $ancestorPath = $candidatePath;
            }
        }

        if ($ancestorPath === null) {
            $store->data[$path] = $value;
            return;
        }

        $current = $store->data[$ancestorPath];
        if (! is_array($current)) {
            $current = [];
        }

        $segments = explode('/', substr($path, strlen($ancestorPath) + 1));
        $this->setNestedArrayValue($current, $segments, $value);
        $store->data[$ancestorPath] = $current;
    }

    private function setNestedArrayValue(array &$target, array $segments, mixed $value): void
    {
        $segment = array_shift($segments);
        if ($segment === null || $segment === '') {
            return;
        }

        if ($segments === []) {
            $target[$segment] = $value;
            return;
        }

        if (! isset($target[$segment]) || ! is_array($target[$segment])) {
            $target[$segment] = [];
        }

        $this->setNestedArrayValue($target[$segment], $segments, $value);
    }
}
