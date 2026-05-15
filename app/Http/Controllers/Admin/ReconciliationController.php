<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function __construct(private FirebaseService $firebase) {}

    public function index(Request $request): View
    {
        $selectedWeek = trim((string) $request->query('iso_year_week', ''));
        $latestReports = $this->firebase->getReconciliationReports(null, 10);

        if ($selectedWeek === '' && ! empty($latestReports)) {
            $selectedWeek = (string) ($latestReports[0]['iso_year_week'] ?? '');
        }

        $reports = $selectedWeek !== ''
            ? $this->firebase->getReconciliationReports($selectedWeek, 10)
            : $latestReports;

        $weekOptions = [];
        for ($i = 0; $i < 10; $i++) {
            $weekOptions[] = now()->subWeeks($i)->format('o_W');
        }

        return view('admin.reconciliation.index', [
            'reports' => $reports,
            'selectedWeek' => $selectedWeek,
            'weekOptions' => $weekOptions,
        ]);
    }

    public function show(Request $request, string $isoYearWeek, string $reportId): View
    {
        $report = $this->firebase->getReconciliationReportById($isoYearWeek, $reportId);
        abort_if(! $report, 404);

        $anomalies = is_array($report['anomalies'] ?? null) ? $report['anomalies'] : [];
        usort($anomalies, fn ($a, $b) => (($b['drift_pct'] ?? 0) <=> ($a['drift_pct'] ?? 0)));

        return view('admin.reconciliation.show', [
            'report' => $report,
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
