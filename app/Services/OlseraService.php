<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OlseraService — Gate layer untuk Olsera POS API.
 *
 * Handles: login/token cache, fetch report data, sync ke local DB.
 * Endpoints discovered: /reportpenjualan/productsalesbysku, /salesreports/allin1salesummarybydate
 */
class OlseraService
{
    protected string $storeSlug = 'matarampetshop';
    protected string $apiBase;
    protected string $oauthUrl = 'https://permissions-api-dash.olsera.co.id/oauth/token';

    public function __construct()
    {
        $this->apiBase = "https://permissions-api-dash.olsera.co.id/api/{$this->storeSlug}/admin/v1/id";
    }

    /**
     * Get valid access token (cached 23 hours, auto-refresh).
     */
    public function getToken(): ?string
    {
        return Cache::remember('olsera_token', 82800, function () {
            $firebase = app(FirebaseService::class);
            $settings = $firebase->getSettings();

            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->timeout(15)
                ->post($this->oauthUrl, [
                    'grant_type' => 'password',
                    'username' => $settings['olsera_email'] ?? 'Matarampetshop@gmail.com',
                    'password' => $settings['olsera_password'] ?? '',
                    'client_id' => 2,
                ]);

            if (! $response->successful()) {
                Log::error('Olsera login failed', ['status' => $response->status()]);
                return null;
            }

            return $response->json('access_token');
        });
    }

    /**
     * Authenticated GET request to Olsera API.
     */
    protected function apiGet(string $endpoint, array $params = [], int $timeout = 30): ?array
    {
        $token = $this->getToken();
        if (! $token) {
            return null;
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->timeout($timeout)->get("{$this->apiBase}{$endpoint}", $params);

        if (! $response->successful()) {
            Log::warning('Olsera API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
            ]);
            return null;
        }

        return $response->json();
    }

    /**
     * Fetch top products by revenue for a date range.
     */
    public function fetchTopProducts(string $from, string $to, int $limit = 20): array
    {
        $data = $this->apiGet('/reportpenjualan/productsalesbysku', [
            'from' => $from,
            'to' => $to,
            'sort' => 'desc',
            'sort_by' => 'total_amount',
            'per_page' => $limit,
            'order_type' => 1,
        ], 60);

        return $data['data'] ?? [];
    }

    /**
     * Fetch sales summary (top groups/categories, profit/loss, payment modes).
     * Note: range max 7 days to avoid timeout.
     */
    public function fetchSalesSummary(string $from, string $to): ?array
    {
        $data = $this->apiGet('/salesreports/allin1salesummarybydate', [
            'from' => $from,
            'to' => $to,
            'per_page' => 50,
            'filter_by' => 0,
            'order_source' => 0,
            'order_source_online' => 0,
            'sort' => 'desc',
            'sort_by' => '',
            'order_type' => 1,
        ], 60);

        return $data['data'] ?? null;
    }

    /**
     * Fetch omzet (total revenue + transaction count + payment breakdown) for a date.
     */
    public function fetchOmzet(string $date): array
    {
        $allOrders = [];
        $page = 1;

        do {
            $data = $this->apiGet('/closeorder', [
                'start_date' => $date,
                'end_date' => $date,
                'per_page' => 100,
                'page' => $page,
                'sort_column' => 'order_date',
                'sort_type' => 'desc',
            ]);

            if (! $data || empty($data['data'])) {
                break;
            }

            $allOrders = array_merge($allOrders, $data['data']);
            $lastPage = $data['meta']['last_page'] ?? 1;
            $page++;
        } while ($page <= $lastPage);

        $totalAmount = 0;
        $paymentBreakdown = [];

        foreach ($allOrders as $order) {
            $amount = (float) ($order['total_amount'] ?? 0);
            $totalAmount += $amount;

            $paymentType = match ($order['payment_type_name'] ?? '') {
                'Kartu Debit' => 'QRIS',
                '' => 'Lainnya',
                default => $order['payment_type_name'],
            };
            $paymentBreakdown[$paymentType] = ($paymentBreakdown[$paymentType] ?? 0) + $amount;
        }

        arsort($paymentBreakdown);

        return [
            'total_transactions' => count($allOrders),
            'total_amount' => $totalAmount,
            'payment_breakdown' => $paymentBreakdown,
        ];
    }

    /**
     * Sync daily sales data to local DB (olsera_daily_sales table).
     * Call this from cron/artisan command.
     */
    public function syncDaily(string $date): bool
    {
        Log::info("Olsera sync starting for {$date}");

        // 1. Fetch top products (by revenue)
        $products = $this->fetchTopProducts($date, $date, 50);
        if (empty($products)) {
            Log::warning("Olsera sync: no product data for {$date}");
        }

        // 2. Fetch summary (categories, profit)
        $summary = $this->fetchSalesSummary($date, $date);

        // 3. Fetch omzet
        $omzet = $this->fetchOmzet($date);

        // Clear existing data for this date
        DB::table('olsera_daily_sales')->where('sale_date', $date)->delete();

        // Insert products
        foreach ($products as $p) {
            DB::table('olsera_daily_sales')->updateOrInsert(
                [
                    'sale_date' => $date,
                    'type' => 'product',
                    'product_id' => $p['product_id'] ?? null,
                    'name' => $p['product_name'] ?? '',
                ],
                [
                    'group_name' => $p['product_group_name'] ?? null,
                    'total_qty' => (float) ($p['total_qty'] ?? 0),
                    'total_amount' => (float) ($p['total_amount'] ?? 0),
                    'total_profit' => (float) ($p['total_profit'] ?? 0),
                    'updated_at' => now(),
                ]
            );
        }

        // Insert categories
        if ($summary && ! empty($summary['data_all_groups']['details'])) {
            foreach ($summary['data_all_groups']['details'] as $g) {
                DB::table('olsera_daily_sales')->updateOrInsert(
                    [
                        'sale_date' => $date,
                        'type' => 'category',
                        'name' => $g['name'] ?? '',
                    ],
                    [
                        'group_name' => $g['name'] ?? '',
                        'total_qty' => (float) ($g['total_qty'] ?? 0),
                        'total_amount' => (float) ($g['total_amount'] ?? 0),
                        'total_profit' => 0,
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // Insert daily summary
        DB::table('olsera_daily_sales')->updateOrInsert(
            [
                'sale_date' => $date,
                'type' => 'summary',
                'name' => 'daily_total',
            ],
            [
                'total_amount' => $omzet['total_amount'],
                'total_transactions' => $omzet['total_transactions'],
                'payment_breakdown' => json_encode($omzet['payment_breakdown']),
                'meta' => $summary ? json_encode([
                    'revenue' => $summary['data_profitloss']['revenue_non_shipping'] ?? 0,
                    'goods_cost' => $summary['data_profitloss']['goods_cost'] ?? 0,
                    'gross_profit' => $summary['data_profitloss']['gross_profit'] ?? 0,
                ]) : null,
                'updated_at' => now(),
            ]
        );

        Log::info("Olsera sync completed for {$date}", [
            'products' => count($products),
            'omzet' => $omzet['total_amount'],
        ]);

        return true;
    }

    // === Query methods (read from local cache) ===

    /**
     * Get top products from cache for a date range.
     */
    public function getTopProducts(string $from, string $to, int $limit = 10): array
    {
        return DB::table('olsera_daily_sales')
            ->where('type', 'product')
            ->whereBetween('sale_date', [$from, $to])
            ->select('name', 'group_name', DB::raw('SUM(total_qty) as total_qty'), DB::raw('SUM(total_amount) as total_amount'), DB::raw('SUM(total_profit) as total_profit'))
            ->groupBy('name', 'group_name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get top categories from cache for a date range.
     */
    public function getTopCategories(string $from, string $to, int $limit = 10): array
    {
        return DB::table('olsera_daily_sales')
            ->where('type', 'category')
            ->whereBetween('sale_date', [$from, $to])
            ->select('name', DB::raw('SUM(total_qty) as total_qty'), DB::raw('SUM(total_amount) as total_amount'))
            ->groupBy('name')
            ->orderByDesc('total_amount')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get omzet summary from cache for a date range.
     */
    public function getOmzetSummary(string $from, string $to): array
    {
        $rows = DB::table('olsera_daily_sales')
            ->where('type', 'summary')
            ->whereBetween('sale_date', [$from, $to])
            ->get();

        $totalAmount = 0;
        $totalTransactions = 0;
        $paymentBreakdown = [];
        $totalProfit = 0;

        foreach ($rows as $row) {
            $totalAmount += (float) $row->total_amount;
            $totalTransactions += (int) $row->total_transactions;

            if ($row->payment_breakdown) {
                $pb = json_decode($row->payment_breakdown, true) ?? [];
                foreach ($pb as $type => $amount) {
                    $paymentBreakdown[$type] = ($paymentBreakdown[$type] ?? 0) + $amount;
                }
            }

            if ($row->meta) {
                $meta = json_decode($row->meta, true) ?? [];
                $totalProfit += (float) ($meta['gross_profit'] ?? 0);
            }
        }

        arsort($paymentBreakdown);

        return [
            'total_amount' => $totalAmount,
            'total_transactions' => $totalTransactions,
            'total_profit' => $totalProfit,
            'payment_breakdown' => $paymentBreakdown,
            'days' => $rows->count(),
        ];
    }
}
