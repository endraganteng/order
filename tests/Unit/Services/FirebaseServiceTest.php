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

    /**
     * @return array{0: Database, 1: object}
     */
    private function makeDatabaseWithState(array $initialState): array
    {
        $store = (object) ['data' => $initialState];
        $references = [];
        $referencePaths = [];

        $database = $this->createMock(Database::class);
        $database->method('getReference')->willReturnCallback(function (string $path) use ($store, &$references, &$referencePaths): Reference {
            if (isset($references[$path])) {
                return $references[$path];
            }

            $reference = $this->createMock(Reference::class);
            $referencePaths[spl_object_id($reference)] = $path;

            $reference->method('getSnapshot')->willReturnCallback(function () use ($store, $path, $reference): Snapshot {
                return new Snapshot($reference, $store->data[$path] ?? null);
            });

            $reference->method('set')->willReturnCallback(function ($value) use ($store, $path, $reference): Reference {
                $store->data[$path] = $value;
                return $reference;
            });

            $reference->method('update')->willReturnCallback(function (array $payload) use ($store, $path, $reference): Reference {
                $current = $store->data[$path] ?? [];
                if (! is_array($current)) {
                    $current = [];
                }

                $store->data[$path] = array_replace($current, $payload);

                return $reference;
            });

            $reference->method('getKey')->willReturn(basename($path));

            $references[$path] = $reference;

            return $reference;
        });

        $database->method('runTransaction')->willReturnCallback(function (callable $callback) use ($store, &$referencePaths) {
            $transaction = new class($store, $referencePaths) {
                public function __construct(private object $store, private array $referencePaths)
                {
                }

                public function snapshot(Reference $reference): Snapshot
                {
                    $path = $this->referencePaths[spl_object_id($reference)] ?? '';
                    return new Snapshot($reference, $this->store->data[$path] ?? null);
                }

                public function set(Reference $reference, mixed $value): void
                {
                    $path = $this->referencePaths[spl_object_id($reference)] ?? null;
                    if ($path === null) {
                        return;
                    }

                    $this->store->data[$path] = $value;
                }
            };

            return $callback($transaction);
        });

        return [$database, $store];
    }
}
