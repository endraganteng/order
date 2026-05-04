<?php

namespace Tests\Unit\Services;

use App\Services\FirebaseService;
use App\Services\FonnteService;
use PHPUnit\Framework\TestCase;

class FonnteServiceTest extends TestCase
{
    public function test_resolve_clock_in_timestamp_prefers_saved_timestamp(): void
    {
        $firebase = $this->createMock(FirebaseService::class);
        $service = new class($firebase) extends FonnteService
        {
            public function exposeResolveClockInTimestamp(?array $attendance, string $date): ?int
            {
                return $this->resolveClockInTimestamp($attendance, $date);
            }
        };

        $resolved = $service->exposeResolveClockInTimestamp([
            'clock_in' => '08:30',
            'clock_in_timestamp' => 1714815000,
        ], '2026-05-04');

        $this->assertSame(1714815000, $resolved);
    }

    public function test_resolve_clock_in_timestamp_falls_back_to_date_plus_clock_in_string(): void
    {
        $firebase = $this->createMock(FirebaseService::class);
        $service = new class($firebase) extends FonnteService
        {
            public function exposeResolveClockInTimestamp(?array $attendance, string $date): ?int
            {
                return $this->resolveClockInTimestamp($attendance, $date);
            }
        };

        $resolved = $service->exposeResolveClockInTimestamp([
            'clock_in' => '08:30',
        ], '2026-05-04');

        $this->assertSame(strtotime('2026-05-04 08:30:00'), $resolved);
    }

    public function test_send_task_reminders_claims_and_completes_durable_general_dispatch(): void
    {
        $firebase = $this->getMockBuilder(FirebaseService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getSettings',
                'getWaiterById',
                'claimTaskReminderDispatch',
                'completeTaskReminderDispatch',
                'releaseTaskReminderDispatch',
            ])
            ->getMock();

        $firebase->method('getSettings')->willReturn([
            'fonnte_enabled' => true,
            'fonnte_api_token' => 'token',
        ]);
        $firebase->method('getWaiterById')->with('waiter-1')->willReturn([
            'phone' => '08123456789',
        ]);
        $firebase->expects($this->once())
            ->method('claimTaskReminderDispatch')
            ->with('waiter-1', '2026-05-04', 'general', 3600, $this->isType('int'))
            ->willReturn(true);
        $firebase->expects($this->once())
            ->method('completeTaskReminderDispatch')
            ->with(
                'waiter-1',
                '2026-05-04',
                'general',
                $this->isType('int'),
                $this->callback(fn (array $metadata): bool => ($metadata['pending_count'] ?? null) === 1)
            );
        $firebase->expects($this->never())->method('releaseTaskReminderDispatch');

        $service = new class($firebase) extends FonnteService
        {
            public array $messages = [];

            public function sendMessage(string $phone, string $message): ?array
            {
                $this->messages[] = compact('phone', 'message');

                return ['status' => true];
            }
        };

        $result = $service->sendTaskReminders('waiter-1', [
            ['status' => 'pending', 'task_type' => 'general', 'title' => 'Bersihkan meja'],
        ], [
            'clock_in_timestamp' => time() - 3700,
        ], '2026-05-04');

        $this->assertSame(['general'], $result['sent']);
        $this->assertCount(1, $service->messages);
        $this->assertSame('08123456789', $service->messages[0]['phone']);
        $this->assertStringContainsString('PENGINGAT TUGAS', $service->messages[0]['message']);
    }
}
