<?php

namespace Tests\Unit\Services;

use App\Services\BonusService;
use App\Services\FirebaseService;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
use Kreait\Firebase\Database\Snapshot;
use PHPUnit\Framework\TestCase;

class BonusServiceTest extends TestCase
{
    public function test_monthly_points_capacity_uses_canonical_defaults(): void
    {
        $service = new BonusService(
            $this->createMock(FirebaseService::class),
            $this->createMock(Database::class)
        );

        $capacity = $service->getMonthlyPointsCapacity($service->getDefaultConfig());

        // Default config: daily=30 (5 disiplin + 10 operasional + 5 attitude + 10 rack_recheck),
        // service & sales monthly (5 per day each), perfect_day_bonus=5.
        $this->assertSame(26, $capacity['working_days']);
        $this->assertSame(30, $capacity['daily_max_points']);
        $this->assertSame(35, $capacity['daily_max_with_perfect']);
        $this->assertSame(130, $capacity['monthly_service_max']);
        $this->assertSame(130, $capacity['monthly_sales_max']);
        // 35*26 daily + 130 service + 130 sales = 910 + 260 = 1170
        $this->assertSame(1170, $capacity['theoretical_max']);
    }

    public function test_waiter_monthly_progress_uses_canonical_totals_and_percentage(): void
    {
        $service = new class($this->createMock(FirebaseService::class), $this->createMock(Database::class)) extends BonusService
        {
            public function getBonusConfig(): array
            {
                return $this->getDefaultConfig();
            }

            public function getMonthlyDailyPoints(string $waiterId, string $month): array
            {
                return [
                    '2026-05-01' => ['daily_total' => 25, 'perfect_day_bonus' => 5],
                    '2026-05-02' => ['daily_total' => 20, 'perfect_day_bonus' => 0],
                ];
            }

            public function getPenaltiesByMonth(string $month, ?string $waiterId = null): array
            {
                return [
                    ['points_deducted' => -15],
                ];
            }

            public function getSalesTarget(string $waiterId, string $month): ?array
            {
                return ['target_amount' => 1000000];
            }

            public function getMonthlyBonusSummary(string $waiterId, string $month): ?array
            {
                return [
                    'service_points' => 30,
                    'sales_points' => 10,
                ];
            }

            public function getLeaderboard(string $month): array
            {
                return ['rankings' => []];
            }
        };

        $progress = $service->getWaiterMonthlyProgress('waiter-1', '2026-05');

        $this->assertSame(45, $progress['total_earned']);
        $this->assertSame(15, $progress['total_penalties']);
        $this->assertSame(-15, $progress['penalty_signed_total']);
        $this->assertSame(30, $progress['service_points']);
        $this->assertSame(10, $progress['sales_points']);
        $this->assertSame(70, $progress['net_points']);
        $this->assertSame(1170, $progress['theoretical_max']);
        $this->assertSame(1, $progress['perfect_days']);
        $this->assertSame(2, $progress['days_scored']);
        // 70 / 1170 * 100 = 5.98... → 6.0
        $this->assertSame(6.0, $progress['percentage']);
    }

    public function test_save_auto_daily_score_skips_existing_admin_override(): void
    {
        $service = new class($this->createMock(FirebaseService::class), $this->createMock(Database::class)) extends BonusService
        {
            public function getBonusConfig(): array
            {
                return $this->getDefaultConfig();
            }

            public function getDailyPoints(string $waiterId, string $date): ?array
            {
                return [
                    'admin_override' => true,
                    'daily_total' => 18,
                    'raw_total' => 13,
                    'perfect_day_bonus' => 5,
                    'categories' => [
                        'discipline' => 5,
                        'operational' => 5,
                        'attitude' => 3,
                    ],
                ];
            }
        };

        $result = $service->saveAutoDailyScore('waiter-1', '2026-05-04', [
            'discipline' => 0,
            'operational' => 0,
            'attitude' => 0,
        ], 'Auto');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['skipped']);
        $this->assertSame(18, $result['daily_total']);
        $this->assertSame('Skipped auto-score because record is admin overridden.', $result['message']);
    }

    public function test_finalize_monthly_bonus_rejects_already_finalized_summary(): void
    {
        $existing = [
            'status' => 'finalized',
            'waiter_id' => 'waiter-1',
            'month' => '2026-05',
            'finalized_at' => 1234567890,
        ];

        $transaction = new class($existing)
        {
            public bool $setCalled = false;

            public function __construct(private array $existing) {}

            public function snapshot($reference): object
            {
                return new class($this->existing)
                {
                    public function __construct(private array $existing) {}

                    public function exists(): bool
                    {
                        return true;
                    }

                    public function getValue(): array
                    {
                        return $this->existing;
                    }
                };
            }

            public function set($reference, $value): void
            {
                $this->setCalled = true;
            }
        };

        $database = $this->createMock(Database::class);
        $reference = $this->createMock(Reference::class);
        $database->method('getReference')->willReturn($reference);
        $database->method('runTransaction')->willReturnCallback(function (callable $callback) use ($transaction) {
            return $callback($transaction);
        });

        $service = new class($this->createMock(FirebaseService::class), $database) extends BonusService
        {
            public function calculateMonthlyBonus(string $waiterId, string $month, ?int $monthlyServicePercentage = null, ?int $monthlySalesPercentage = null): array
            {
                throw new \RuntimeException('calculateMonthlyBonus should not be called for an already finalized month');
            }
        };

        $result = $service->finalizeMonthlyBonus('waiter-1', '2026-05');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['already_finalized']);
        $this->assertSame('Bonus bulan ini sudah difinalisasi.', $result['message']);
        $this->assertFalse($transaction->setCalled);
    }

    public function test_get_leaderboard_returns_live_ranking_for_active_waiters_only(): void
    {
        $firebase = $this->createMock(FirebaseService::class);
        $firebase->method('getActiveWaiters')->willReturn([
            ['id' => 'waiter-1', 'name' => 'Active One', 'is_active' => true],
            ['id' => 'waiter-3', 'name' => 'Active Three', 'is_active' => true],
        ]);

        // Live leaderboard memanggil calculateMonthlyBonus per waiter aktif
        // (atau getMonthlyBonusSummary kalau ada). Subclass override agar
        // tidak perlu mock-up panjang Firebase reads.
        $service = new class($firebase, $this->createMock(Database::class)) extends BonusService
        {
            public function getMonthlyBonusSummary(string $waiterId, string $month): ?array
            {
                return match ($waiterId) {
                    'waiter-1' => [
                        'net_points' => 80, 'points_percentage' => 8.5,
                        'perfect_days' => 1, 'penalty_count' => 0,
                        'total_bonus' => 300000, 'points_bonus' => 300000, 'sales_bonus' => 0,
                    ],
                    'waiter-3' => [
                        'net_points' => 60, 'points_percentage' => 6.5,
                        'perfect_days' => 0, 'penalty_count' => 1,
                        'total_bonus' => 200000, 'points_bonus' => 200000, 'sales_bonus' => 0,
                    ],
                    default => null,
                };
            }
        };

        $leaderboard = $service->getLeaderboard('2026-05');

        $this->assertSame('2026-05', $leaderboard['month']);
        $this->assertTrue($leaderboard['live'] ?? false);
        $this->assertSame(2, $leaderboard['total_waiters']);
        $this->assertSame(['waiter-1', 'waiter-3'], array_column($leaderboard['rankings'], 'waiter_id'));
        $this->assertSame([1, 2], array_column($leaderboard['rankings'], 'rank'));
        $this->assertSame([300000, 200000], array_column($leaderboard['rankings'], 'total_bonus'));
        $this->assertSame([80, 60], array_column($leaderboard['rankings'], 'total_points'));
    }

    public function test_get_leaderboard_returns_empty_rankings_when_no_active_waiters(): void
    {
        $firebase = $this->createMock(FirebaseService::class);
        $firebase->method('getActiveWaiters')->willReturn([]);

        $service = new BonusService($firebase, $this->createMock(Database::class));

        $leaderboard = $service->getLeaderboard('2026-06');

        $this->assertSame('2026-06', $leaderboard['month']);
        $this->assertSame(0, $leaderboard['total_waiters']);
        $this->assertSame([], $leaderboard['rankings']);
        $this->assertTrue($leaderboard['live'] ?? false);
    }
}
