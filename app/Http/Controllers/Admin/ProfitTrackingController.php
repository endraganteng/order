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
        $mode = $request->input('mode', 'harian');
        $startDate = $request->input('start_date', Carbon::now()->startOfWeek()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfWeek()->format('Y-m-d'));

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

        return view('admin.profit-tracking.index', compact(
            'grouped',
            'summaries',
            'startDate',
            'endDate',
            'mode'
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
}
