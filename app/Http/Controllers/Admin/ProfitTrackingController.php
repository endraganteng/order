<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyProductTracking;
use App\Services\FinanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProfitTrackingController extends Controller
{
    public function index(Request $request)
    {
        $finance = app(FinanceService::class);
        $trackingStartDate = $finance->getSetting('profit_tracking_start_date');

        $mode = $request->input('mode', 'harian');
        $startDate = $request->input('start_date', Carbon::now()->startOfWeek()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfWeek()->format('Y-m-d'));

        // Enforce start date tidak lebih awal dari tracking start date
        if ($trackingStartDate && $startDate < $trackingStartDate) {
            $startDate = $trackingStartDate;
        }

        $trackings = DailyProductTracking::forDateRange($startDate, $endDate)
            ->orderBy('tracking_date', 'desc')
            ->orderBy('product_name')
            ->get();

        // Group by date
        $grouped = $trackings->groupBy(function ($item) {
            return $item->tracking_date->format('Y-m-d');
        });

        // Summary per date
        $summaries = [];
        foreach ($grouped as $date => $items) {
            $summaries[$date] = [
                'total_stok_masuk' => $items->sum('stok_masuk_total'),
                'total_penjualan' => $items->sum('penjualan_nominal'),
                'total_profit' => $items->sum('profit'),
            ];
        }

        // Carry-over: previous day sisa_stok per [date][product_name]
        $carryOver = [];
        $allDates = array_keys($grouped->toArray());

        if (!empty($allDates)) {
            // Get all unique dates from current period
            $uniqueDates = collect($allDates)->unique()->values();

            foreach ($uniqueDates as $date) {
                $prevDate = Carbon::parse($date)->subDay()->format('Y-m-d');
                $prevDayRows = DailyProductTracking::where('tracking_date', $prevDate)->get();
                foreach ($prevDayRows as $row) {
                    $carryOver[$date][$row->product_name] = $row->sisa_stok_qty;
                }
            }
        }

        return view('admin.profit-tracking.index', compact(
            'grouped',
            'summaries',
            'startDate',
            'endDate',
            'mode',
            'carryOver',
            'trackingStartDate'
        ));
    }

    public function updatePenjualan(Request $request)
    {
        $request->validate([
            'tracking_date' => 'required|date',
            'items' => 'required|array',
            'items.*.product_name' => 'required|string',
            'items.*.penjualan_nominal' => 'required|numeric|min:0',
        ]);

        $trackingDate = $request->input('tracking_date');
        $items = $request->input('items');

        foreach ($items as $item) {
            DailyProductTracking::updateOrCreate(
                [
                    'tracking_date' => $trackingDate,
                    'product_name' => $item['product_name'],
                ],
                [
                    'penjualan_nominal' => $item['penjualan_nominal'],
                ]
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Data penjualan berhasil disimpan.',
            ]);
        }

        return redirect()->back()->with('success', 'Data penjualan berhasil disimpan.');
    }

    public function syncNow(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::today()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::today()->format('Y-m-d'));

        try {
            $financeService = app(FinanceService::class);
            $result = $financeService->syncDaily($startDate, $endDate, 'manual');

            $message = 'Sync berhasil. Status: ' . ($result['status'] ?? 'done');
        } catch (\Exception $e) {
            $message = 'Sync gagal: ' . $e->getMessage();
        }

        return redirect()->back()->with('info', $message);
    }

    public function report(Request $request)
    {
        $finance = app(FinanceService::class);
        $trackingStartDate = $finance->getSetting('profit_tracking_start_date');

        $mode = $request->input('mode', 'harian');
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));

        // Enforce start date tidak lebih awal dari tracking start date
        if ($trackingStartDate && $startDate < $trackingStartDate) {
            $startDate = $trackingStartDate;
        }

        $trackings = DailyProductTracking::forDateRange($startDate, $endDate)
            ->orderBy('tracking_date', 'asc')
            ->orderBy('product_name')
            ->get();

        // Chart data
        $chartData = [];
        foreach ($trackings as $item) {
            $chartData[] = [
                'date' => $item->tracking_date->format('Y-m-d'),
                'product_name' => $item->product_name,
                'profit' => (float) $item->profit,
                'penjualan' => (float) $item->penjualan_nominal,
                'stok_masuk' => (float) $item->stok_masuk_total,
            ];
        }

        // Comparison: previous period same duration
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $durationDays = $start->diffInDays($end) + 1;

        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($durationDays - 1);

        $currentTotal = $trackings->sum('profit');
        $previousTotal = (float) DailyProductTracking::forDateRange(
            $prevStart->format('Y-m-d'),
            $prevEnd->format('Y-m-d')
        )->sum('profit');

        $changePercent = 0;
        if ($previousTotal != 0) {
            $changePercent = round((($currentTotal - $previousTotal) / abs($previousTotal)) * 100, 1);
        }

        $comparison = [
            'current_total' => (float) $currentTotal,
            'previous_total' => $previousTotal,
            'change_percent' => $changePercent,
            'prev_start' => $prevStart->format('Y-m-d'),
            'prev_end' => $prevEnd->format('Y-m-d'),
        ];

        // Summary per product
        $productSummary = [];
        $groupedByProduct = $trackings->groupBy('product_name');
        foreach ($groupedByProduct as $productName => $items) {
            $productSummary[] = [
                'product_name' => $productName,
                'total_stok_masuk_qty' => $items->sum('stok_masuk_qty'),
                'total_stok_masuk_rp' => $items->sum('stok_masuk_total'),
                'total_penjualan' => $items->sum('penjualan_nominal'),
                'total_profit' => $items->sum('profit'),
                'days_tracked' => $items->count(),
            ];
        }

        // Group by date for detail table (like index but read-only)
        $grouped = $trackings->groupBy(function ($item) {
            return $item->tracking_date->format('Y-m-d');
        });

        // Daily summaries
        $dailySummaries = [];
        foreach ($grouped as $date => $items) {
            $dailySummaries[$date] = [
                'total_stok_masuk' => $items->sum('stok_masuk_total'),
                'total_penjualan' => $items->sum('penjualan_nominal'),
                'total_profit' => $items->sum('profit'),
            ];
        }

        // Carry-over for report
        $carryOver = [];
        $allDates = array_keys($grouped->toArray());
        if (!empty($allDates)) {
            foreach ($allDates as $date) {
                $prevDate = Carbon::parse($date)->subDay()->format('Y-m-d');
                $prevDayRows = DailyProductTracking::where('tracking_date', $prevDate)->get();
                foreach ($prevDayRows as $row) {
                    $carryOver[$date][$row->product_name] = $row->sisa_stok_qty;
                }
            }
        }

        return view('admin.profit-tracking.report', compact(
            'chartData',
            'comparison',
            'productSummary',
            'grouped',
            'dailySummaries',
            'carryOver',
            'startDate',
            'endDate',
            'mode',
            'trackings'
        ));
    }

    public function setOpeningStock(Request $request)
    {
        $request->validate([
            'opening_date' => 'required|date',
            'items' => 'required|array',
            'items.*.product_name' => 'required|string',
            'items.*.sisa_stok_qty' => 'required|numeric|min:0',
        ]);

        $date = $request->input('opening_date');
        $items = $request->input('items');

        foreach ($items as $item) {
            DailyProductTracking::updateOrCreate(
                [
                    'tracking_date' => $date,
                    'product_name' => $item['product_name'],
                ],
                [
                    'sisa_stok_qty' => $item['sisa_stok_qty'],
                ]
            );
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Saldo awal berhasil disimpan untuk tanggal ' . $date,
            ]);
        }

        return redirect()->back()->with('success', 'Saldo awal berhasil disimpan untuk tanggal ' . $date);
    }

    public function saveSettings(Request $request)
    {
        $request->validate([
            'profit_tracking_start_date' => 'required|date',
        ]);

        $finance = app(FinanceService::class);
        $finance->setSetting('profit_tracking_start_date', $request->input('profit_tracking_start_date'));

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Tanggal mulai tracking disimpan: ' . $request->input('profit_tracking_start_date'),
            ]);
        }

        return redirect()->back()->with('success', 'Tanggal mulai tracking disimpan.');
    }
}
