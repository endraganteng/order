<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FinanceService
{
    // ─── Settings ───────────────────────────────────────────────

    public function getSetting(string $key, $default = null): ?string
    {
        return DB::table('finance_settings')->where('key', $key)->value('value') ?? $default;
    }

    public function setSetting(string $key, ?string $value): void
    {
        DB::table('finance_settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }

    public function getSettings(array $keys): array
    {
        $rows = DB::table('finance_settings')->whereIn('key', $keys)->pluck('value', 'key')->toArray();
        $result = [];
        foreach ($keys as $k) {
            $result[$k] = $rows[$k] ?? null;
        }
        return $result;
    }

    public function getAllSettings(): array
    {
        return DB::table('finance_settings')->pluck('value', 'key')->toArray();
    }

    public function saveSettings(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->setSetting($key, $value);
        }
    }

    // ─── API Client ─────────────────────────────────────────────

    protected function getApiUrl(): string
    {
        return rtrim($this->getSetting('api_domain', config('finance.api_url')), '/');
    }

    protected function getApiToken(): string
    {
        return $this->getSetting('api_token', config('finance.api_token')) ?? '';
    }

    public function testConnection(): array
    {
        $url = $this->getApiUrl();
        $token = $this->getApiToken();

        if (!$url || !$token) {
            return ['success' => false, 'message' => 'API URL atau Token belum diatur.'];
        }

        try {
            $response = Http::timeout(10)->withHeaders(['X-Internal-Token' => $token])
                ->get($url . '/api/finance/summary');

            if ($response->successful() && ($response->json('success') === true)) {
                return ['success' => true, 'message' => 'Koneksi berhasil.', 'data' => $response->json('data')];
            }

            return ['success' => false, 'message' => $response->json('message') ?? 'Response tidak valid.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Gagal koneksi: ' . $e->getMessage()];
        }
    }

    protected function apiGet(string $endpoint, array $params = []): array
    {
        $url = $this->getApiUrl();
        $token = $this->getApiToken();

        if (!$url || !$token) {
            return ['success' => false, 'message' => 'API belum dikonfigurasi.'];
        }

        try {
            $response = Http::timeout((int) config('finance.sync_timeout', 30))
                ->withHeaders(['X-Internal-Token' => $token])
                ->get($url . $endpoint, $params);

            return $response->json() ?? ['success' => false, 'message' => 'Response kosong.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Sync ───────────────────────────────────────────────────

    public function syncDaily(string $from, string $to, string $triggeredBy = 'manual'): array
    {
        $start = microtime(true);
        $synced = 0;
        $failed = 0;
        $errors = [];

        // Sync summary/daily data
        $dailyResult = $this->apiGet('/api/finance/daily', ['dari' => $from, 'sampai' => $to]);
        if (!($dailyResult['success'] ?? false)) {
            return $this->logSync('manual', $from, $to, 'failed', 0, 0, $dailyResult['message'] ?? 'API error', $triggeredBy, $start);
        }

        foreach ($dailyResult['data'] ?? [] as $row) {
            try {
                DB::table('finance_daily_data')->updateOrInsert(
                    ['tanggal' => $row['tanggal']],
                    [
                        'penjualan_tunai' => $row['penjualan_tunai'] ?? 0,
                        'penjualan_qris' => $row['penjualan_qris'] ?? 0,
                        'total_pendapatan' => $row['total_pendapatan'] ?? 0,
                        'total_retur' => $row['total_retur'] ?? 0,
                        'total_pengeluaran' => $row['total_pengeluaran'] ?? 0,
                        'total_pengeluaran_shift' => $row['total_pengeluaran_shift'] ?? $row['total_pengeluaran'] ?? 0,
                        'pendapatan_bersih' => $row['pendapatan_bersih'] ?? 0,
                        'jumlah_shift' => $row['jumlah_shift'] ?? 0,
                        'sync_status' => 'synced',
                        'updated_at' => now(),
                    ]
                );
                $synced++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "Daily {$row['tanggal']}: {$e->getMessage()}";
            }
        }

        // Sync shifts
        $shiftResult = $this->apiGet('/api/finance/shifts', ['dari' => $from, 'sampai' => $to]);
        if ($shiftResult['success'] ?? false) {
            foreach ($shiftResult['data'] ?? [] as $shift) {
                try {
                    DB::table('finance_shifts')->updateOrInsert(
                        ['api_shift_id' => $shift['id']],
                        [
                            'tanggal' => $shift['tanggal'],
                            'shift_number' => $shift['shift_number'],
                            'loket' => $shift['loket'] ?? null,
                            'kasir' => $shift['kasir'] ?? null,
                            'modal_awal' => $shift['modal_awal'] ?? 0,
                            'penjualan_tunai' => $shift['penjualan_tunai'] ?? 0,
                            'penjualan_qris' => $shift['penjualan_qris'] ?? 0,
                            'total_pengeluaran' => $shift['total_pengeluaran'] ?? 0,
                            'selisih' => $shift['selisih'] ?? 0,
                            'status' => $shift['status'] ?? null,
                            'updated_at' => now(),
                        ]
                    );
                    $synced++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Shift: {$e->getMessage()}";
                }
            }
        }

        $status = $failed === 0 ? 'success' : ($synced > 0 ? 'partial_success' : 'failed');

        // Auto-create cash mutations from daily data based on account mappings
        $this->createMutationsFromSync($from, $to);

        return $this->logSync('manual', $from, $to, $status, $synced, $failed, implode('; ', $errors) ?: null, $triggeredBy, $start);
    }

    /**
     * Buat mutasi kas otomatis dari data daily yang sudah di-sync.
     * Mapping: penjualan_tunai → akun kas (via mapping), penjualan_qris → akun kas, pengeluaran → akun kas.
     */
    protected function createMutationsFromSync(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to) {
            $dailyRows = DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->get();

            foreach ($dailyRows as $row) {
                // Penjualan Tunai → income ke akun yang di-mapping
                $tunaiAccountId = $this->resolveAccountMapping('penjualan_tunai');
                if ($tunaiAccountId && $row->penjualan_tunai > 0) {
                    $this->upsertMutation($tunaiAccountId, 'income', $row->penjualan_tunai, 'Penjualan tunai ' . $row->tanggal, $row->tanggal, 'sync', $row->id);
                }

                // Penjualan QRIS → income ke akun yang di-mapping
                $qrisAccountId = $this->resolveAccountMapping('penjualan_qris');
                if ($qrisAccountId && $row->penjualan_qris > 0) {
                    $this->upsertMutation($qrisAccountId, 'income', $row->penjualan_qris, 'Penjualan QRIS ' . $row->tanggal, $row->tanggal, 'sync', $row->id);
                }

                // Pengeluaran Shift → expense dari akun yang di-mapping (termasuk retur, karena fisik uang keluar)
                $expAccountId = $this->resolveAccountMapping('pengeluaran_shift');
                if ($expAccountId && ($row->total_pengeluaran_shift ?? $row->total_pengeluaran) > 0) {
                    $this->upsertMutation($expAccountId, 'expense', (int) ($row->total_pengeluaran_shift ?? $row->total_pengeluaran), 'Pengeluaran shift ' . $row->tanggal, $row->tanggal, 'sync', $row->id);
                }

                // Selisih kas (jika ada) → koreksi saldo Kas Laci
                $totalSelisih = (int) DB::table('finance_shifts')->where('tanggal', $row->tanggal)->sum('selisih');
                if ($totalSelisih != 0 && $tunaiAccountId) {
                    if ($totalSelisih < 0) {
                        // Uang kurang → expense (selisih negatif = uang hilang)
                        $this->upsertMutation($tunaiAccountId, 'expense', abs($totalSelisih), 'Selisih kas ' . $row->tanggal, $row->tanggal, 'sync_selisih', $row->id);
                    } else {
                        // Uang lebih → income (selisih positif = uang lebih)
                        $this->upsertMutation($tunaiAccountId, 'income', $totalSelisih, 'Selisih kas ' . $row->tanggal, $row->tanggal, 'sync_selisih', $row->id);
                    }
                }
            }
        });
    }

    /**
     * Insert atau update mutasi kas (cegah duplikat berdasarkan reference).
     */
    protected function upsertMutation(int $accountId, string $type, int $amount, string $description, string $date, string $refType, int $refId): void
    {
        $existing = DB::table('cash_mutations')
            ->where('cash_account_id', $accountId)
            ->where('type', $type)
            ->where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->where('transaction_date', $date)
            ->where('description', $description)
            ->first();

        if ($existing) {
            // Update amount jika berubah
            if ((int) $existing->amount !== $amount) {
                $diff = $amount - (int) $existing->amount;
                if ($type === 'income' || $type === 'transfer_in') {
                    DB::table('cash_accounts')->where('id', $accountId)->increment('balance', $diff);
                } else {
                    DB::table('cash_accounts')->where('id', $accountId)->decrement('balance', $diff);
                }
                $newBalance = (int) DB::table('cash_accounts')->where('id', $accountId)->value('balance');
                DB::table('cash_mutations')->where('id', $existing->id)->update(['amount' => $amount, 'balance_after' => $newBalance, 'updated_at' => now()]);
            }
            return;
        }

        // Insert baru + update saldo akun
        if ($type === 'income' || $type === 'transfer_in') {
            DB::table('cash_accounts')->where('id', $accountId)->increment('balance', $amount);
        } else {
            DB::table('cash_accounts')->where('id', $accountId)->decrement('balance', $amount);
        }

        $newBalance = (int) DB::table('cash_accounts')->where('id', $accountId)->value('balance');

        DB::table('cash_mutations')->insert([
            'cash_account_id' => $accountId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $newBalance,
            'description' => $description,
            'reference_type' => $refType,
            'reference_id' => $refId,
            'transaction_date' => $date,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function logSync(string $type, string $from, string $to, string $status, int $synced, int $failed, ?string $error, string $triggeredBy, float $start): array
    {
        $duration = (int) ((microtime(true) - $start) * 1000);
        $id = DB::table('finance_sync_logs')->insertGetId([
            'type' => $type,
            'sync_date_from' => $from,
            'sync_date_to' => $to,
            'status' => $status,
            'records_synced' => $synced,
            'records_failed' => $failed,
            'error_message' => $error,
            'triggered_by' => $triggeredBy,
            'duration_ms' => $duration,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['success' => $status !== 'failed', 'status' => $status, 'synced' => $synced, 'failed' => $failed, 'duration_ms' => $duration, 'id' => $id];
    }

    protected function resolveCategoryMapping(string $lineType): ?int
    {
        $row = DB::table('finance_api_mappings')
            ->where('mapping_type', 'category')
            ->where('api_key', 'line_type')
            ->where('api_value', $lineType)
            ->where('is_active', true)
            ->first();

        return $row ? (int) $row->target_id : null;
    }

    public function resolveAccountMapping(string $source): ?int
    {
        $row = DB::table('finance_api_mappings')
            ->where('mapping_type', 'cash_account')
            ->where('api_key', 'source')
            ->where('api_value', $source)
            ->where('is_active', true)
            ->first();

        return $row ? (int) $row->target_id : null;
    }


    // ─── Dashboard ──────────────────────────────────────────────

    public function getDashboardSummary(?string $date = null): array
    {
        $date = $date ?: date('Y-m-d');
        $row = DB::table('finance_daily_data')->where('tanggal', $date)->first();

        if (!$row) {
            return ['penjualan_tunai' => 0, 'penjualan_qris' => 0, 'total_pendapatan' => 0, 'total_pengeluaran' => 0, 'pendapatan_bersih' => 0, 'jumlah_shift' => 0];
        }

        return (array) $row;
    }

    public function getMonthSummary(string $month): array
    {
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        return [
            'penjualan_tunai' => (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('penjualan_tunai'),
            'penjualan_qris' => (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('penjualan_qris'),
            'total_pendapatan' => (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('total_pendapatan'),
            'total_retur' => (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('total_retur'),
            'total_pengeluaran' => (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('total_pengeluaran'),
            'pendapatan_bersih' => (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('pendapatan_bersih'),
            'jumlah_shift' => (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('jumlah_shift'),
            'total_selisih' => (int) DB::table('finance_shifts')->whereBetween('tanggal', [$from, $to])->sum('selisih'),
            'days_synced' => DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->count(),
        ];
    }

    public function getDailyData(string $from, string $to): array
    {
        return DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->orderBy('tanggal')->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function getLastSync(): ?array
    {
        $row = DB::table('finance_sync_logs')->orderByDesc('created_at')->first();
        return $row ? (array) $row : null;
    }

    public function getNeedReviewCount(): int
    {
        return DB::table('finance_expense_items')->where('status', 'need_review')->count();
    }

    // ─── Categories ─────────────────────────────────────────────

    public function getCategories(?string $type = null, ?bool $activeOnly = null): array
    {
        $q = DB::table('finance_categories');
        if ($type) $q->where('type', $type);
        if ($activeOnly !== null) $q->where('is_active', $activeOnly);
        return $q->orderBy('sort_order')->orderBy('name')->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function getCategory(int $id): ?array
    {
        $row = DB::table('finance_categories')->find($id);
        return $row ? (array) $row : null;
    }

    public function createCategory(array $data): int
    {
        return DB::table('finance_categories')->insertGetId([
            'name' => $data['name'],
            'type' => $data['type'],
            'parent_id' => $data['parent_id'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateCategory(int $id, array $data): void
    {
        DB::table('finance_categories')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
    }

    public function toggleCategory(int $id): void
    {
        $current = DB::table('finance_categories')->where('id', $id)->value('is_active');
        DB::table('finance_categories')->where('id', $id)->update(['is_active' => !$current, 'updated_at' => now()]);
    }

    // ─── Allocations ────────────────────────────────────────────

    public function getAllocations(?bool $activeOnly = null): array
    {
        $q = DB::table('finance_allocations')->join('finance_categories', 'finance_allocations.finance_category_id', '=', 'finance_categories.id');
        if ($activeOnly !== null) $q->where('finance_allocations.is_active', $activeOnly);
        return $q->select('finance_allocations.*', 'finance_categories.name as category_name')
            ->orderBy('finance_allocations.effective_date', 'desc')->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function createAllocation(array $data): int
    {
        return DB::table('finance_allocations')->insertGetId([
            'finance_category_id' => $data['finance_category_id'],
            'percentage' => $data['percentage'],
            'effective_date' => $data['effective_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateAllocation(int $id, array $data): void
    {
        DB::table('finance_allocations')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
    }

    public function deleteAllocation(int $id): void
    {
        DB::table('finance_allocations')->where('id', $id)->delete();
    }

    public function simulateAllocation(int $totalPendapatan): array
    {
        $allocations = DB::table('finance_allocations')
            ->join('finance_categories', 'finance_allocations.finance_category_id', '=', 'finance_categories.id')
            ->where('finance_allocations.is_active', true)
            ->where('finance_allocations.effective_date', '<=', date('Y-m-d'))
            ->where(fn($q) => $q->whereNull('finance_allocations.end_date')->orWhere('finance_allocations.end_date', '>=', date('Y-m-d')))
            ->select('finance_allocations.*', 'finance_categories.name as category_name')
            ->get();

        return $allocations->map(fn($a) => [
            'category_name' => $a->category_name,
            'percentage' => (float) $a->percentage,
            'amount' => (int) round($totalPendapatan * $a->percentage / 100),
        ])->toArray();
    }

    // ─── Cash Accounts ──────────────────────────────────────────

    public function getCashAccounts(?bool $activeOnly = null): array
    {
        $q = DB::table('cash_accounts');
        if ($activeOnly !== null) $q->where('is_active', $activeOnly);
        return $q->orderBy('sort_order')->orderBy('name')->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function getCashAccount(int $id): ?array
    {
        $row = DB::table('cash_accounts')->find($id);
        return $row ? (array) $row : null;
    }

    public function createCashAccount(array $data): int
    {
        return DB::table('cash_accounts')->insertGetId([
            'name' => $data['name'],
            'code' => $data['code'],
            'balance' => $data['balance'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateCashAccount(int $id, array $data): void
    {
        DB::table('cash_accounts')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
    }

    public function toggleCashAccount(int $id): void
    {
        $current = DB::table('cash_accounts')->where('id', $id)->value('is_active');
        DB::table('cash_accounts')->where('id', $id)->update(['is_active' => !$current, 'updated_at' => now()]);
    }

    public function resetAccountBalance(int $id): void
    {
        DB::transaction(function () use ($id) {
            DB::table('cash_accounts')->where('id', $id)->update(['balance' => 0, 'updated_at' => now()]);
            DB::table('cash_mutations')->where('cash_account_id', $id)->delete();
        });
    }


    // ─── Transfers ──────────────────────────────────────────────

    public function getTransfers(?string $status = null): array
    {
        $q = DB::table('cash_transfers')
            ->join('cash_accounts as fa', 'cash_transfers.from_account_id', '=', 'fa.id')
            ->join('cash_accounts as ta', 'cash_transfers.to_account_id', '=', 'ta.id')
            ->select('cash_transfers.*', 'fa.name as from_name', 'ta.name as to_name');
        if ($status) $q->where('cash_transfers.status', $status);
        return $q->orderByDesc('cash_transfers.created_at')->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function createTransfer(array $data): int
    {
        return DB::table('cash_transfers')->insertGetId([
            'from_account_id' => $data['from_account_id'],
            'to_account_id' => $data['to_account_id'],
            'amount' => $data['amount'],
            'fee' => $data['fee'] ?? 0,
            'status' => $data['status'] ?? 'draft',
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function approveTransfer(int $id, string $approvedBy): bool
    {
        $transfer = DB::table('cash_transfers')->find($id);
        if (!$transfer || $transfer->status !== 'pending') return false;

        return DB::transaction(function () use ($transfer, $id, $approvedBy) {
            $fromBalance = DB::table('cash_accounts')->where('id', $transfer->from_account_id)->value('balance');
            $totalDeduct = $transfer->amount + $transfer->fee;

            if ($fromBalance < $totalDeduct) return false;

            DB::table('cash_accounts')->where('id', $transfer->from_account_id)->decrement('balance', $totalDeduct);
            DB::table('cash_accounts')->where('id', $transfer->to_account_id)->increment('balance', $transfer->amount);

            DB::table('cash_transfers')->where('id', $id)->update(['status' => 'approved', 'approved_by' => $approvedBy, 'approved_at' => now(), 'updated_at' => now()]);

            // Record mutations
            $fromBalanceAfter = $fromBalance - $totalDeduct;
            $toBalanceAfter = DB::table('cash_accounts')->where('id', $transfer->to_account_id)->value('balance');

            $mutations = [
                ['cash_account_id' => $transfer->from_account_id, 'type' => 'transfer_out', 'amount' => $transfer->amount, 'balance_after' => $fromBalance - $transfer->amount, 'description' => 'Transfer keluar', 'reference_type' => 'transfer', 'reference_id' => $id, 'transaction_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
                ['cash_account_id' => $transfer->to_account_id, 'type' => 'transfer_in', 'amount' => $transfer->amount, 'balance_after' => $toBalanceAfter, 'description' => 'Transfer masuk', 'reference_type' => 'transfer', 'reference_id' => $id, 'transaction_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
            ];

            // Fee dicatat sebagai expense terpisah
            if ($transfer->fee > 0) {
                $mutations[] = ['cash_account_id' => $transfer->from_account_id, 'type' => 'expense', 'amount' => $transfer->fee, 'balance_after' => $fromBalanceAfter, 'description' => 'Biaya transfer', 'reference_type' => 'transfer_fee', 'reference_id' => $id, 'transaction_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()];
            }

            DB::table('cash_mutations')->insert($mutations);

            return true;
        });
    }

    public function rejectTransfer(int $id, string $rejectedBy): void
    {
        DB::table('cash_transfers')->where('id', $id)->where('status', 'pending')->update(['status' => 'rejected', 'approved_by' => $rejectedBy, 'approved_at' => now(), 'updated_at' => now()]);
    }

    // ─── Mutations ──────────────────────────────────────────────

    public function getMutations(?int $accountId = null, ?string $from = null, ?string $to = null, int $limit = 50, int $offset = 0): array
    {
        $q = DB::table('cash_mutations')
            ->join('cash_accounts', 'cash_mutations.cash_account_id', '=', 'cash_accounts.id')
            ->leftJoin('finance_categories', 'cash_mutations.finance_category_id', '=', 'finance_categories.id')
            ->select('cash_mutations.*', 'cash_accounts.name as account_name', 'finance_categories.name as category_name');

        if ($accountId) $q->where('cash_mutations.cash_account_id', $accountId);
        if ($from) $q->where('cash_mutations.transaction_date', '>=', $from);
        if ($to) $q->where('cash_mutations.transaction_date', '<=', $to);

        $total = $q->count();
        $data = $q->orderByDesc('cash_mutations.created_at')->offset($offset)->limit($limit)->get()->map(fn($r) => (array) $r)->toArray();

        return ['data' => $data, 'total' => $total];
    }

    // ─── API Mappings ───────────────────────────────────────────

    public function getMappings(string $type): array
    {
        $q = DB::table('finance_api_mappings')->where('mapping_type', $type);

        if ($type === 'category') {
            $q->leftJoin('finance_categories', 'finance_api_mappings.target_id', '=', 'finance_categories.id')
              ->select('finance_api_mappings.*', 'finance_categories.name as target_name');
        } else {
            $q->leftJoin('cash_accounts', 'finance_api_mappings.target_id', '=', 'cash_accounts.id')
              ->select('finance_api_mappings.*', 'cash_accounts.name as target_name');
        }

        return $q->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function createMapping(array $data): int
    {
        return DB::table('finance_api_mappings')->insertGetId([
            'mapping_type' => $data['mapping_type'],
            'api_key' => $data['api_key'],
            'api_value' => $data['api_value'],
            'target_id' => $data['target_id'],
            'is_active' => $data['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateMapping(int $id, array $data): void
    {
        DB::table('finance_api_mappings')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
    }

    public function deleteMapping(int $id): void
    {
        DB::table('finance_api_mappings')->where('id', $id)->delete();
    }

    // ─── Sync Logs ──────────────────────────────────────────────

    public function getSyncLogs(int $limit = 20, int $offset = 0): array
    {
        $total = DB::table('finance_sync_logs')->count();
        $data = DB::table('finance_sync_logs')->orderByDesc('created_at')->offset($offset)->limit($limit)->get()->map(fn($r) => (array) $r)->toArray();
        return ['data' => $data, 'total' => $total];
    }

    // ─── Shifts ─────────────────────────────────────────────────

    public function getShifts(?string $from = null, ?string $to = null): array
    {
        $q = DB::table('finance_shifts');
        if ($from) $q->where('tanggal', '>=', $from);
        if ($to) $q->where('tanggal', '<=', $to);
        return $q->orderByDesc('tanggal')->orderBy('shift_number')->get()->map(fn($r) => (array) $r)->toArray();
    }

    // ─── Need Review ────────────────────────────────────────────

    public function getNeedReviewItems(int $limit = 50, int $offset = 0): array
    {
        $total = DB::table('finance_expense_items')->where('status', 'need_review')->count();
        $data = DB::table('finance_expense_items')->where('status', 'need_review')
            ->orderByDesc('tanggal')->offset($offset)->limit($limit)->get()->map(fn($r) => (array) $r)->toArray();
        return ['data' => $data, 'total' => $total];
    }

    public function resolveReviewItem(int $id, ?int $categoryId, string $action = 'resolve'): void
    {
        $update = ['updated_at' => now()];
        if ($action === 'ignore') {
            $update['status'] = 'ignored';
        } else {
            $update['status'] = 'synced';
            $update['finance_category_id'] = $categoryId;
        }
        DB::table('finance_expense_items')->where('id', $id)->update($update);
    }

    // ─── Audit Log ──────────────────────────────────────────────

    public function logAudit(string $action, string $module, ?int $recordId = null, ?array $oldValues = null, ?array $newValues = null): void
    {
        DB::table('finance_audit_logs')->insert([
            'user_id' => session('admin_id', 'system'),
            'user_name' => session('admin_name', 'System'),
            'user_role' => 'supervisor',
            'action' => $action,
            'module' => $module,
            'record_id' => $recordId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function getAuditLogs(?string $module = null, int $limit = 50, int $offset = 0): array
    {
        $q = DB::table('finance_audit_logs');
        if ($module) $q->where('module', $module);
        $total = $q->count();
        $data = $q->orderByDesc('created_at')->offset($offset)->limit($limit)->get()->map(fn($r) => (array) $r)->toArray();
        return ['data' => $data, 'total' => $total];
    }

    // ─── Hutang Supplier ───────────────────────────────────────

    public function getDebts(?string $status = null): array
    {
        $q = DB::table('finance_debts');
        if ($status) $q->where('status', $status);
        return $q->orderByDesc('debt_date')->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function getDebt(int $id): ?array
    {
        $row = DB::table('finance_debts')->find($id);
        return $row ? (array) $row : null;
    }

    public function createDebt(array $data): int
    {
        return DB::table('finance_debts')->insertGetId([
            'supplier_name' => $data['supplier_name'],
            'amount' => $data['amount'],
            'paid' => 0,
            'description' => $data['description'] ?? null,
            'debt_date' => $data['debt_date'],
            'due_date' => $data['due_date'] ?? null,
            'status' => 'unpaid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function payDebt(int $debtId, int $accountId, int $amount, string $date, ?string $notes = null): bool
    {
        return DB::transaction(function () use ($debtId, $accountId, $amount, $date, $notes) {
            $debt = DB::table('finance_debts')->find($debtId);
            if (!$debt || $debt->status === 'paid') return false;

            $remaining = $debt->amount - $debt->paid;
            if ($amount > $remaining) $amount = $remaining;

            DB::table('finance_debt_payments')->insert([
                'finance_debt_id' => $debtId,
                'cash_account_id' => $accountId,
                'amount' => $amount,
                'payment_date' => $date,
                'notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $newPaid = $debt->paid + $amount;
            $newStatus = $newPaid >= $debt->amount ? 'paid' : 'partial';
            DB::table('finance_debts')->where('id', $debtId)->update(['paid' => $newPaid, 'status' => $newStatus, 'updated_at' => now()]);

            DB::table('cash_accounts')->where('id', $accountId)->decrement('balance', $amount);
            $newBalance = (int) DB::table('cash_accounts')->where('id', $accountId)->value('balance');

            DB::table('cash_mutations')->insert([
                'cash_account_id' => $accountId,
                'type' => 'expense',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => 'Bayar hutang: ' . $debt->supplier_name,
                'reference_type' => 'debt_payment',
                'reference_id' => $debtId,
                'transaction_date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;
        });
    }

    public function getDebtPayments(int $debtId): array
    {
        return DB::table('finance_debt_payments')
            ->join('cash_accounts', 'finance_debt_payments.cash_account_id', '=', 'cash_accounts.id')
            ->where('finance_debt_id', $debtId)
            ->select('finance_debt_payments.*', 'cash_accounts.name as account_name')
            ->orderByDesc('payment_date')->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function getDebtSummary(): array
    {
        $total = (int) DB::table('finance_debts')->whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('amount - paid'));
        $count = DB::table('finance_debts')->whereIn('status', ['unpaid', 'partial'])->count();
        $dueSoon = DB::table('finance_debts')->whereIn('status', ['unpaid', 'partial'])->whereNotNull('due_date')->where('due_date', '<=', now()->addDays(7)->toDateString())->count();
        return ['total_hutang' => $total, 'jumlah_hutang' => $count, 'jatuh_tempo_minggu_ini' => $dueSoon];
    }

    // ─── Pengeluaran Manual ────────────────────────────────────

    public function recordExpense(array $data): int
    {
        $accountId = (int) $data['cash_account_id'];
        $categoryId = (int) $data['finance_category_id'];
        $amount = (int) $data['amount'];
        $description = $data['description'];
        $date = $data['transaction_date'] ?? date('Y-m-d');

        return DB::transaction(function () use ($accountId, $categoryId, $amount, $description, $date) {
            $balance = (int) DB::table('cash_accounts')->where('id', $accountId)->value('balance');
            if ($balance < $amount) {
                throw new \Exception('Saldo tidak cukup. Saldo: Rp ' . number_format($balance, 0, ',', '.') . ', Pengeluaran: Rp ' . number_format($amount, 0, ',', '.'));
            }

            DB::table('cash_accounts')->where('id', $accountId)->decrement('balance', $amount);
            $newBalance = $balance - $amount;

            return DB::table('cash_mutations')->insertGetId([
                'cash_account_id' => $accountId,
                'finance_category_id' => $categoryId,
                'type' => 'expense',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'reference_type' => 'manual',
                'transaction_date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    // ─── Budget vs Realisasi ────────────────────────────────────

    public function getBudgetRealization(string $month): array
    {
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $totalPendapatan = (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('total_pendapatan');

        $allocations = DB::table('finance_allocations')
            ->join('finance_categories', 'finance_allocations.finance_category_id', '=', 'finance_categories.id')
            ->where('finance_allocations.is_active', true)
            ->where('finance_allocations.effective_date', '<=', $to)
            ->where(fn($q) => $q->whereNull('finance_allocations.end_date')->orWhere('finance_allocations.end_date', '>=', $from))
            ->select('finance_allocations.*', 'finance_categories.name as category_name')
            ->get();

        $result = [];
        foreach ($allocations as $alloc) {
            $budget = (int) round($totalPendapatan * $alloc->percentage / 100);
            $realisasi = (int) DB::table('cash_mutations')
                ->where('finance_category_id', $alloc->finance_category_id)
                ->where('type', 'expense')
                ->whereBetween('transaction_date', [$from, $to])
                ->sum('amount');

            $sisa = $budget - $realisasi;
            $pctUsed = $budget > 0 ? round(($realisasi / $budget) * 100, 1) : 0;

            $result[] = [
                'category_id' => $alloc->finance_category_id,
                'category_name' => $alloc->category_name,
                'percentage' => (float) $alloc->percentage,
                'budget' => $budget,
                'realisasi' => $realisasi,
                'sisa' => $sisa,
                'pct_used' => $pctUsed,
            ];
        }

        return ['total_pendapatan' => $totalPendapatan, 'allocations' => $result];
    }

    // ─── Reports ────────────────────────────────────────────────

    public function getMonthlyReport(string $month): array
    {
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $daily = DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->orderBy('tanggal')->get()->map(fn($r) => (array) $r)->toArray();
        $summary = $this->getMonthSummary($month);
        $accounts = $this->getCashAccounts(true);

        return ['daily' => $daily, 'summary' => $summary, 'accounts' => $accounts];
    }

    public function getAccountBalanceReport(?string $month = null): array
    {
        $month = $month ?: date('Y-m');
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $accounts = DB::table('cash_accounts')->where('is_active', true)->orderBy('sort_order')->get()->map(fn($r) => (array) $r)->toArray();

        foreach ($accounts as &$acc) {
            $acc['period_income'] = (int) DB::table('cash_mutations')->where('cash_account_id', $acc['id'])->where('type', 'income')->whereBetween('transaction_date', [$from, $to])->sum('amount');
            $acc['period_expense'] = (int) DB::table('cash_mutations')->where('cash_account_id', $acc['id'])->where('type', 'expense')->whereBetween('transaction_date', [$from, $to])->sum('amount');
            $acc['period_transfer_in'] = (int) DB::table('cash_mutations')->where('cash_account_id', $acc['id'])->where('type', 'transfer_in')->whereBetween('transaction_date', [$from, $to])->sum('amount');
            $acc['period_transfer_out'] = (int) DB::table('cash_mutations')->where('cash_account_id', $acc['id'])->where('type', 'transfer_out')->whereBetween('transaction_date', [$from, $to])->sum('amount');
        }

        return $accounts;
    }

    // ─── Tutup Buku Bulanan ────────────────────────────────────

    public function isMonthClosed(string $month): bool
    {
        return DB::table('finance_monthly_closings')->where('month', $month)->where('status', 'closed')->exists();
    }

    public function getClosing(string $month): ?array
    {
        $row = DB::table('finance_monthly_closings')->where('month', $month)->first();
        return $row ? (array) $row : null;
    }

    public function getClosings(): array
    {
        return DB::table('finance_monthly_closings')->orderByDesc('month')->get()->map(fn($r) => (array) $r)->toArray();
    }

    public function closeMonth(string $month, string $closedBy, ?string $notes = null): array
    {
        if ($this->isMonthClosed($month)) {
            return ['success' => false, 'message' => 'Bulan ini sudah ditutup.'];
        }

        $snapshot = $this->generateSnapshot($month);

        DB::table('finance_monthly_closings')->updateOrInsert(
            ['month' => $month],
            [
                'status' => 'closed',
                'snapshot' => json_encode($snapshot),
                'closed_by' => $closedBy,
                'closed_at' => now(),
                'notes' => $notes,
                'updated_at' => now(),
            ]
        );

        $this->logAudit('close_month', 'monthly_closing', null, null, ['month' => $month]);

        return ['success' => true, 'snapshot' => $snapshot];
    }

    public function reopenMonth(string $month, string $reopenedBy): array
    {
        $closing = $this->getClosing($month);
        if (! $closing || $closing['status'] !== 'closed') {
            return ['success' => false, 'message' => 'Bulan ini tidak dalam status closed.'];
        }

        DB::table('finance_monthly_closings')->where('month', $month)->update([
            'status' => 'reopened',
            'reopened_by' => $reopenedBy,
            'reopened_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logAudit('reopen_month', 'monthly_closing', null, null, ['month' => $month]);

        return ['success' => true];
    }

    protected function generateSnapshot(string $month): array
    {
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        $income = (int) DB::table('cash_mutations')->whereIn('type', ['income'])->whereBetween('transaction_date', [$from, $to])->sum('amount');
        $expense = (int) DB::table('cash_mutations')->whereIn('type', ['expense'])->whereBetween('transaction_date', [$from, $to])->sum('amount');

        // Breakdown expense by category
        $expenseByCategory = DB::table('cash_mutations')
            ->leftJoin('finance_categories', 'cash_mutations.finance_category_id', '=', 'finance_categories.id')
            ->where('cash_mutations.type', 'expense')
            ->whereBetween('cash_mutations.transaction_date', [$from, $to])
            ->selectRaw('COALESCE(finance_categories.name, "Tanpa Kategori") as category, SUM(cash_mutations.amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        // Payroll expense (from payroll_transactions)
        $payrollExpense = (int) DB::table('payroll_transactions')
            ->where('type', 'withdrawal')->where('status', 'approved')
            ->whereBetween('processed_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('amount');

        // Account balances at end of month
        $accountBalances = DB::table('cash_accounts')->where('is_active', true)
            ->select('name', 'balance')->get()->map(fn($r) => (array) $r)->toArray();

        // Debt summary
        $debtPaid = (int) DB::table('finance_debt_payments')->whereBetween('payment_date', [$from, $to])->sum('amount');
        $debtNew = (int) DB::table('finance_debts')->whereBetween('debt_date', [$from, $to])->sum('amount');

        $monthSummary = $this->getMonthSummary($month);

        return [
            'month' => $month,
            'pendapatan' => [
                'penjualan_tunai' => $monthSummary['penjualan_tunai'],
                'penjualan_qris' => $monthSummary['penjualan_qris'],
                'total' => $monthSummary['total_pendapatan'],
            ],
            'pengeluaran' => [
                'total' => $expense,
                'by_category' => $expenseByCategory,
                'payroll' => $payrollExpense,
            ],
            'laba_bersih' => $income - $expense,
            'saldo_akun' => $accountBalances,
            'hutang' => [
                'baru' => $debtNew,
                'dibayar' => $debtPaid,
            ],
            'days_synced' => $monthSummary['days_synced'],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    // ─── Laporan Laba Rugi ─────────────────────────────────────

    public function getLabaRugi(string $month): array
    {
        $from = $month . '-01';
        $to = date('Y-m-t', strtotime($from));

        // Pendapatan
        $penjualanTunai = (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('penjualan_tunai');
        $penjualanQris = (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('penjualan_qris');
        $pendapatanLain = (int) DB::table('cash_mutations')
            ->where('type', 'income')
            ->where('reference_type', '!=', 'sync')
            ->whereBetween('transaction_date', [$from, $to])
            ->sum('amount');
        $totalPendapatan = $penjualanTunai + $penjualanQris + $pendapatanLain;

        // Retur
        $totalRetur = (int) DB::table('finance_daily_data')->whereBetween('tanggal', [$from, $to])->sum('total_retur');
        $pendapatanBersih = $totalPendapatan - $totalRetur;

        // Pengeluaran by category
        $expenses = DB::table('cash_mutations')
            ->leftJoin('finance_categories', 'cash_mutations.finance_category_id', '=', 'finance_categories.id')
            ->where('cash_mutations.type', 'expense')
            ->whereBetween('cash_mutations.transaction_date', [$from, $to])
            ->selectRaw('COALESCE(finance_categories.name, "Lain-lain") as category, SUM(cash_mutations.amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();

        $totalExpense = array_sum(array_column($expenses, 'total'));
        $labaBersih = $pendapatanBersih - $totalExpense;

        return [
            'month' => $month,
            'pendapatan' => [
                ['label' => 'Penjualan Tunai', 'amount' => $penjualanTunai],
                ['label' => 'Penjualan QRIS', 'amount' => $penjualanQris],
                ['label' => 'Pendapatan Lain', 'amount' => $pendapatanLain],
            ],
            'total_pendapatan' => $totalPendapatan,
            'retur' => $totalRetur,
            'pendapatan_bersih' => $pendapatanBersih,
            'pengeluaran' => $expenses,
            'total_pengeluaran' => $totalExpense,
            'laba_bersih' => $labaBersih,
            'margin' => $totalPendapatan > 0 ? round(($labaBersih / $totalPendapatan) * 100, 1) : 0,
        ];
    }
}
