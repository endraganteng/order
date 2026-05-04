<?php

namespace Tests\Unit\Services;

use App\Services\BonusService;
use App\Services\FirebaseService;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Database\Reference;
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

        $this->assertSame(26, $capacity['working_days']);
        $this->assertSame(20, $capacity['daily_max_points']);
        $this->assertSame(25, $capacity['daily_max_with_perfect']);
        $this->assertSame(130, $capacity['monthly_service_max']);
        $this->assertSame(130, $capacity['monthly_sales_max']);
        $this->assertSame(910, $capacity['theoretical_max']);
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
        $this->assertSame(910, $progress['theoretical_max']);
        $this->assertSame(1, $progress['perfect_days']);
        $this->assertSame(2, $progress['days_scored']);
        $this->assertSame(7.7, $progress['percentage']);
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
}
