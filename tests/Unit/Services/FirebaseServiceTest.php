<?php

namespace Tests\Unit\Services;

use App\Services\FirebaseService;
use Kreait\Firebase\Contract\Auth;
use Kreait\Firebase\Contract\Database;
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
}
