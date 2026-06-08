<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReconciliationReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        $selectedWeek = trim((string) $request->query('iso_year_week', ''));

        $latestReports = ReconciliationReport::orderByDesc('created_at')->limit(10)->get();

        if ($selectedWeek === '' && $latestReports->isNotEmpty()) {
            $selectedWeek = (string) $latestReports->first()->iso_year_week;
        }

        $reports = $selectedWeek !== ''
            ? ReconciliationReport::forWeek($selectedWeek)->orderByDesc('created_at')->limit(10)->get()
            : $latestReports;

        $weekOptions = [];
        for ($i = 0; $i < 10; $i++) {
            $weekOptions[] = now()->subWeeks($i)->format('o_W');
        }

        return view('admin.reconciliation.index', [
            'reports' => $reports->toArray(),
            'selectedWeek' => $selectedWeek,
            'weekOptions' => $weekOptions,
        ]);
    }

    public function show(Request $request, string $isoYearWeek, string $reportId): View
    {
        $report = ReconciliationReport::where('iso_year_week', $isoYearWeek)
            ->where('id', $reportId)
            ->first();

        abort_if(! $report, 404);

        $reportArray = $report->toArray();
        $anomalies = is_array($reportArray['anomalies'] ?? null) ? $reportArray['anomalies'] : [];
        usort($anomalies, fn ($a, $b) => (($b['drift_pct'] ?? 0) <=> ($a['drift_pct'] ?? 0)));

        return view('admin.reconciliation.show', [
            'report' => $reportArray,
            'anomalies' => $anomalies,
            'isoYearWeek' => $isoYearWeek,
            'reportId' => $reportId,
            'severityFilter' => (string) $request->query('severity', 'all'),
        ]);
    }

    public function runNow(Request $request): RedirectResponse
    {
        Artisan::call('firebase:reconcile-stock');

        return redirect()
            ->route('admin.reconciliation.index')
            ->with('success', 'Reconciliation berhasil dijalankan.');
    }
}
