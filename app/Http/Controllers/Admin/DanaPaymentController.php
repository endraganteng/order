<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * DanaPaymentController (Admin)
 *
 * Riwayat & laporan pembayaran DANA Bisnis yang masuk via webhook PayHook.
 *  - Filter rentang tanggal + search nama pengirim
 *  - Pagination (default 50/halaman)
 *  - Summary: total nominal, jumlah transaksi, top sender
 *  - Export CSV untuk rentang tanggal yang sedang difilter
 */
class DanaPaymentController extends Controller
{
    public function __construct(private Database $firebaseDb)
    {
    }

    /**
     * Halaman utama riwayat pembayaran.
     *
     * Query params:
     *   - from=YYYY-MM-DD (default: hari ini)
     *   - to=YYYY-MM-DD   (default: hari ini)
     *   - search=string   (filter by sender_name LIKE)
     *   - per_page=N      (default 50, max 200)
     */
    public function index(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);
        $search = trim((string) $request->query('search', ''));
        $perPage = max(10, min((int) $request->query('per_page', 50), 200));

        $query = DB::table('dana_payment_notifications')
            ->whereBetween('received_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('sender_name', 'LIKE', $like)
                  ->orWhere('notification_text', 'LIKE', $like)
                  ->orWhere('payhook_reference', 'LIKE', $like);
            });
        }

        // Summary (sebelum pagination)
        $summaryQuery = clone $query;
        $summary = [
            'total_amount' => (int) $summaryQuery->sum('amount'),
            'total_count'  => (int) $summaryQuery->count(),
            'avg_amount'   => 0,
            'date_range'   => $from === $to ? $from : "$from s/d $to",
        ];
        if ($summary['total_count'] > 0) {
            $summary['avg_amount'] = (int) round($summary['total_amount'] / $summary['total_count']);
        }

        // Top 5 sender (berdasarkan total nominal)
        $topSendersQuery = clone $query;
        $topSenders = $topSendersQuery
            ->select('sender_name', DB::raw('COUNT(*) as tx_count'), DB::raw('SUM(amount) as tx_total'))
            ->whereNotNull('sender_name')
            ->where('sender_name', '!=', '')
            ->groupBy('sender_name')
            ->orderByDesc('tx_total')
            ->limit(5)
            ->get();

        // Daily breakdown (untuk chart kalau range > 1 hari)
        $dailyBreakdownQuery = clone $query;
        $dailyBreakdown = $dailyBreakdownQuery
            ->select(DB::raw('DATE(received_at) as tanggal'), DB::raw('COUNT(*) as tx_count'), DB::raw('SUM(amount) as tx_total'))
            ->groupBy(DB::raw('DATE(received_at)'))
            ->orderBy('tanggal')
            ->get();

        // Paginated data
        $payments = $query
            ->orderBy('received_at', 'desc')
            ->paginate($perPage)
            ->appends($request->query());

        return view('admin.dana_payments.index', [
            'payments'       => $payments,
            'summary'        => $summary,
            'topSenders'     => $topSenders,
            'dailyBreakdown' => $dailyBreakdown,
            'filters'        => [
                'from'     => $from,
                'to'       => $to,
                'search'   => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Export CSV pembayaran sesuai filter (TANPA pagination).
     */
    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->resolveDateRange($request);
        $search = trim((string) $request->query('search', ''));

        $query = DB::table('dana_payment_notifications')
            ->whereBetween('received_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('sender_name', 'LIKE', $like)
                  ->orWhere('notification_text', 'LIKE', $like)
                  ->orWhere('payhook_reference', 'LIKE', $like);
            });
        }

        $filename = "dana_payments_{$from}_to_{$to}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 supaya Excel buka tanpa garbled chars
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'ID',
                'Tanggal Diterima',
                'Tanggal Notif (HP)',
                'Nominal',
                'Sumber',
                'Pengirim',
                'Reference',
                'Notification Title',
                'Notification Text',
            ]);

            $query->orderBy('received_at', 'desc')
                ->chunkById(500, function ($rows) use ($handle) {
                    foreach ($rows as $r) {
                        fputcsv($handle, [
                            $r->id,
                            $r->received_at,
                            $r->notified_at,
                            $r->amount,
                            $r->source,
                            $r->sender_name,
                            $r->payhook_reference,
                            $r->notification_title,
                            $r->notification_text,
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Detail satu pembayaran (modal/JSON).
     */
    public function show($id)
    {
        $row = DB::table('dana_payment_notifications')->where('id', (int) $id)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Tidak ditemukan'], 404);
        }

        // Decode raw_payload kalau ada
        $rawPayload = null;
        if (! empty($row->raw_payload)) {
            $rawPayload = json_decode($row->raw_payload, true);
        }

        return response()->json([
            'success' => true,
            'payment' => [
                'id'                  => (int) $row->id,
                'payhook_reference'   => $row->payhook_reference,
                'amount'              => (int) $row->amount,
                'source'              => $row->source,
                'package_name'        => $row->package_name,
                'sender_name'         => $row->sender_name,
                'notification_title'  => $row->notification_title,
                'notification_text'   => $row->notification_text,
                'notified_at'         => $row->notified_at,
                'received_at'         => $row->received_at,
                'firebase_key'        => $row->firebase_key,
                'raw_payload'         => $rawPayload,
            ],
        ]);
    }

    /**
     * Reset SEMUA riwayat pembayaran DANA.
     *
     * DESTRUCTIVE — truncate tabel + hapus node /dana_payments di Firebase.
     * Memerlukan dua-step konfirmasi:
     *   1. Modal di UI (user klik tombol Reset → modal warning)
     *   2. Field `confirmation` body request harus berisi string "RESET DANA"
     *
     * Audit: log ke Laravel log channel 'default' dengan info admin yang trigger.
     */
    public function reset(Request $request)
    {
        $confirmation = (string) $request->input('confirmation', '');
        if ($confirmation !== 'RESET DANA') {
            return response()->json([
                'success' => false,
                'message' => 'Konfirmasi tidak valid. Ketik persis "RESET DANA" untuk konfirmasi.',
            ], 422);
        }

        // Hitung dulu sebelum truncate (untuk laporan + audit)
        $beforeCount = (int) DB::table('dana_payment_notifications')->count();
        $beforeTotal = (int) DB::table('dana_payment_notifications')->sum('amount');

        // Hapus dari MySQL
        try {
            DB::table('dana_payment_notifications')->truncate();
        } catch (\Throwable $e) {
            Log::error('DanaPaymentController@reset: gagal truncate MySQL', [
                'error' => $e->getMessage(),
                'admin_id' => session('admin_id'),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus dari database: ' . $e->getMessage(),
            ], 500);
        }

        // Hapus node Firebase /dana_payments (best-effort, jangan fail seluruh request kalau Firebase down)
        $firebaseOk = true;
        $firebaseError = null;
        try {
            $this->firebaseDb->getReference('dana_payments')->remove();
        } catch (\Throwable $e) {
            $firebaseOk = false;
            $firebaseError = $e->getMessage();
            Log::warning('DanaPaymentController@reset: Firebase /dana_payments remove gagal', [
                'error' => $firebaseError,
            ]);
        }

        // Audit trail
        Log::warning('DanaPaymentController@reset: SEMUA riwayat DANA dihapus', [
            'admin_id'      => session('admin_id'),
            'admin_name'    => session('admin_name'),
            'admin_email'   => session('admin_email'),
            'ip'            => $request->ip(),
            'user_agent'    => (string) $request->userAgent(),
            'before_count'  => $beforeCount,
            'before_total'  => $beforeTotal,
            'firebase_ok'   => $firebaseOk,
        ]);

        return response()->json([
            'success'        => true,
            'message'        => "Berhasil menghapus {$beforeCount} pembayaran DANA (total Rp " . number_format($beforeTotal, 0, ',', '.') . ").",
            'deleted_count'  => $beforeCount,
            'deleted_total'  => $beforeTotal,
            'firebase_ok'    => $firebaseOk,
            'firebase_error' => $firebaseError,
        ]);
    }

    /**
     * Default: hari ini (from=today, to=today).
     * Auto-swap kalau from > to.
     *
     * @return array{0:string,1:string} [from, to] dalam format Y-m-d
     */
    private function resolveDateRange(Request $request): array
    {
        $today = Carbon::now()->format('Y-m-d');
        $from = (string) $request->query('from', $today);
        $to = (string) $request->query('to', $today);

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = $today;
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $today;
        }

        // Swap kalau terbalik
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [$from, $to];
    }
}
