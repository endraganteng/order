<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Database;

class KasbonService
{
    protected FirebaseService $firebase;

    protected Database $database;

    protected ?FonnteService $fonnte;

    protected PayrollService $payroll;

    public function __construct(FirebaseService $firebase, Database $database, PayrollService $payroll, ?FonnteService $fonnte = null)
    {
        $this->firebase = $firebase;
        $this->database = $database;
        $this->payroll = $payroll;
        try {
            $this->fonnte = $fonnte ?? app(FonnteService::class);
        } catch (\Throwable $e) {
            $this->fonnte = null;
        }
    }

    // =========================================================================
    //  CONFIG
    // =========================================================================

    public function getConfig(): array
    {
        $rows = DB::table('kasbon_configs')->pluck('value', 'key')->toArray();

        return array_merge([
            'default_limit_percent' => '30',
            'kasbon_limit_fixed' => '0',
            'min_kasbon_amount' => '50000',
            'max_active_kasbon' => '1',
            'auto_deduct_enabled' => '1',
        ], $rows);
    }

    public function updateConfig(array $patch): void
    {
        $allowed = ['default_limit_percent', 'kasbon_limit_fixed', 'min_kasbon_amount', 'max_active_kasbon', 'auto_deduct_enabled'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $patch)) {
                DB::table('kasbon_configs')->updateOrInsert(
                    ['key' => $key],
                    ['value' => (string) $patch[$key], 'updated_at' => now()]
                );
            }
        }
    }

    // =========================================================================
    //  LIMIT CALCULATION
    // =========================================================================

    public function calculateAvailableLimit(string $waiterId): array
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        if (! $waiter) {
            return ['limit' => 0, 'used' => 0, 'available' => 0, 'error' => 'Waiter tidak ditemukan'];
        }

        $config = $this->getConfig();
        $monthlySalary = (int) ($waiter['monthly_salary'] ?? 0);
        $limitPercent = (int) ($waiter['kasbon_limit_percent'] ?? $config['default_limit_percent']);
        $limitFixed = (int) $config['kasbon_limit_fixed'];

        // Prorated: (salary / 30) * days_worked_this_month * percent / 100
        $daysWorked = (int) date('j'); // hari ke-berapa bulan ini
        $proratedLimit = ($monthlySalary > 0)
            ? (int) floor(($monthlySalary / 30) * $daysWorked * $limitPercent / 100)
            : $limitFixed;

        // Jika salary ada, tambahkan fixed sebagai bonus limit
        $totalLimit = ($monthlySalary > 0) ? $proratedLimit + $limitFixed : $limitFixed;

        // Kasbon aktif yang masih berjalan
        $usedAmount = (int) DB::table('kasbons')
            ->where('waiter_id', $waiterId)
            ->where('status', 'active')
            ->sum('remaining');

        $available = max(0, $totalLimit - $usedAmount);

        return [
            'limit' => $totalLimit,
            'used' => $usedAmount,
            'available' => $available,
            'monthly_salary' => $monthlySalary,
            'days_worked' => $daysWorked,
            'limit_percent' => $limitPercent,
        ];
    }

    // =========================================================================
    //  CREATE (langsung cair)
    // =========================================================================

    public function create(string $waiterId, int $amount, string $reason, string $createdBy): array
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        if (! $waiter) {
            return ['success' => false, 'message' => 'Data karyawan tidak ditemukan.'];
        }
        if (empty($waiter['kasbon_enabled'])) {
            return ['success' => false, 'message' => 'Fitur kasbon belum diaktifkan untuk karyawan ini.'];
        }

        $config = $this->getConfig();
        $minAmount = (int) $config['min_kasbon_amount'];
        if ($amount < $minAmount) {
            return ['success' => false, 'message' => "Minimal kasbon Rp " . number_format($minAmount, 0, ',', '.')];
        }

        // Cek max active
        $maxActive = (int) $config['max_active_kasbon'];
        $activeCount = DB::table('kasbons')
            ->where('waiter_id', $waiterId)
            ->where('status', 'active')
            ->count();
        if ($activeCount >= $maxActive) {
            return ['success' => false, 'message' => "Sudah ada {$activeCount} kasbon aktif. Maksimal {$maxActive}."];
        }

        // Cek limit
        $limitInfo = $this->calculateAvailableLimit($waiterId);
        if ($amount > $limitInfo['available']) {
            return ['success' => false, 'message' => 'Melebihi limit kasbon. Tersedia: Rp ' . number_format($limitInfo['available'], 0, ',', '.')];
        }

        // Langsung cair — potong saldo payroll (boleh negatif)
        return DB::transaction(function () use ($waiterId, $waiter, $amount, $reason, $createdBy) {
            $newBalance = $this->payroll->adjustBalancePublic($waiterId, -$amount);

            $txId = DB::table('payroll_transactions')->insertGetId([
                'waiter_id' => $waiterId,
                'waiter_name' => (string) ($waiter['name'] ?? ''),
                'type' => 'kasbon_disbursement',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'status' => 'completed',
                'note' => 'Kasbon: ' . ($reason ?: '-'),
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $kasbonId = DB::table('kasbons')->insertGetId([
                'waiter_id' => $waiterId,
                'waiter_name' => (string) ($waiter['name'] ?? ''),
                'amount' => $amount,
                'remaining' => $amount,
                'reason' => $reason,
                'status' => 'active',
                'created_by' => $createdBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Trigger flag untuk update portal waiter
            $this->triggerWaiterFlag($waiterId);

            // Notifikasi WA ke supervisor
            $this->notifySupervisorKasbonCreated($waiter, $amount, $reason);

            return [
                'success' => true,
                'kasbon_id' => $kasbonId,
                'tx_id' => $txId,
                'balance_after' => $newBalance,
                'message' => 'Kasbon berhasil dicairkan.',
            ];
        });
    }

    // =========================================================================
    //  CANCEL
    // =========================================================================

    public function cancel(int $kasbonId, string $cancelledBy): array
    {
        $kasbon = DB::table('kasbons')->find($kasbonId);
        if (! $kasbon) {
            return ['success' => false, 'message' => 'Kasbon tidak ditemukan.'];
        }
        if ($kasbon->status !== 'active') {
            return ['success' => false, 'message' => 'Hanya kasbon aktif yang bisa dibatalkan.'];
        }
        // Hanya bisa cancel jika belum ada potongan (remaining = amount)
        if ($kasbon->remaining != $kasbon->amount) {
            return ['success' => false, 'message' => 'Kasbon sudah ada potongan, tidak bisa dibatalkan. Gunakan write-off.'];
        }

        return DB::transaction(function () use ($kasbon, $cancelledBy) {
            DB::table('kasbons')->where('id', $kasbon->id)->update([
                'status' => 'cancelled',
                'cancelled_by' => $cancelledBy,
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

            // Kembalikan saldo
            $newBalance = $this->payroll->adjustBalancePublic($kasbon->waiter_id, (int) $kasbon->amount);

            DB::table('payroll_transactions')->insertGetId([
                'waiter_id' => $kasbon->waiter_id,
                'waiter_name' => $kasbon->waiter_name,
                'type' => 'manual_credit',
                'amount' => (int) $kasbon->amount,
                'balance_after' => $newBalance,
                'status' => 'completed',
                'note' => 'Pembatalan kasbon #' . $kasbon->id,
                'created_by' => $cancelledBy,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->triggerWaiterFlag($kasbon->waiter_id);

            return ['success' => true, 'message' => 'Kasbon dibatalkan, saldo dikembalikan.', 'balance_after' => $newBalance];
        });
    }

    // =========================================================================
    //  WRITE-OFF
    // =========================================================================

    public function writeOff(int $kasbonId, string $reason, string $writtenOffBy): array
    {
        $kasbon = DB::table('kasbons')->find($kasbonId);
        if (! $kasbon) {
            return ['success' => false, 'message' => 'Kasbon tidak ditemukan.'];
        }
        if ($kasbon->status !== 'active') {
            return ['success' => false, 'message' => 'Hanya kasbon aktif yang bisa di-write-off.'];
        }

        DB::table('kasbons')->where('id', $kasbon->id)->update([
            'status' => 'written_off',
            'written_off_by' => $writtenOffBy,
            'written_off_at' => now(),
            'written_off_reason' => $reason,
            'updated_at' => now(),
        ]);

        $this->triggerWaiterFlag($kasbon->waiter_id);
        $this->notifySupervisorKasbonWrittenOff($kasbon, $reason);

        return ['success' => true, 'message' => 'Kasbon di-write-off.'];
    }

    // =========================================================================
    //  AUTO-DEDUCT (dipanggil dari PayrollService setelah credit)
    // =========================================================================

    public function autoDeductFromCredit(string $waiterId, int $creditAmount): array
    {
        $config = $this->getConfig();
        if (empty($config['auto_deduct_enabled']) || $config['auto_deduct_enabled'] === '0') {
            return ['deducted' => 0, 'payments' => []];
        }

        // Ambil kasbon aktif FIFO (oldest first), lock for update
        $activeKasbons = DB::table('kasbons')
            ->where('waiter_id', $waiterId)
            ->where('status', 'active')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->get();

        if ($activeKasbons->isEmpty()) {
            return ['deducted' => 0, 'payments' => []];
        }

        $sisaCredit = $creditAmount;
        $totalDeducted = 0;
        $payments = [];

        foreach ($activeKasbons as $kasbon) {
            if ($sisaCredit <= 0) {
                break;
            }

            $potongan = min($sisaCredit, (int) $kasbon->remaining);
            $newRemaining = (int) $kasbon->remaining - $potongan;

            // Potong balance
            $newBalance = $this->payroll->adjustBalancePublic($waiterId, -$potongan);

            // Catat payroll transaction
            $txId = DB::table('payroll_transactions')->insertGetId([
                'waiter_id' => $waiterId,
                'waiter_name' => $kasbon->waiter_name,
                'type' => 'kasbon_deduct',
                'amount' => $potongan,
                'balance_after' => $newBalance,
                'status' => 'completed',
                'note' => 'Potongan kasbon #' . $kasbon->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Catat kasbon payment
            DB::table('kasbon_payments')->insert([
                'kasbon_id' => $kasbon->id,
                'waiter_id' => $waiterId,
                'amount' => $potongan,
                'remaining_after' => $newRemaining,
                'source' => 'auto_deduct',
                'payroll_tx_id' => $txId,
                'note' => null,
                'created_at' => now(),
            ]);

            // Update kasbon remaining
            $updateData = [
                'remaining' => $newRemaining,
                'updated_at' => now(),
            ];
            if ($newRemaining === 0) {
                $updateData['status'] = 'paid_off';
                $updateData['paid_off_at'] = now();
            }
            DB::table('kasbons')->where('id', $kasbon->id)->update($updateData);

            $payments[] = [
                'kasbon_id' => $kasbon->id,
                'amount' => $potongan,
                'remaining_after' => $newRemaining,
                'paid_off' => $newRemaining === 0,
            ];

            $sisaCredit -= $potongan;
            $totalDeducted += $potongan;

            // Notifikasi jika lunas
            if ($newRemaining === 0) {
                $this->notifySupervisorKasbonPaidOff($kasbon);
            }
        }

        if ($totalDeducted > 0) {
            $this->triggerWaiterFlag($waiterId);
        }

        return ['deducted' => $totalDeducted, 'payments' => $payments];
    }

    // =========================================================================
    //  QUERIES
    // =========================================================================

    public function listAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $query = DB::table('kasbons')->orderByDesc('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['waiter_id'])) {
            $query->where('waiter_id', $filters['waiter_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $total = $query->count();
        $items = $query->offset($offset)->limit($limit)->get()->map(fn($r) => (array) $r)->toArray();

        return ['items' => $items, 'total' => $total];
    }

    public function getById(int $id): ?array
    {
        $row = DB::table('kasbons')->find($id);
        return $row ? (array) $row : null;
    }

    public function getPayments(int $kasbonId): array
    {
        return DB::table('kasbon_payments')
            ->where('kasbon_id', $kasbonId)
            ->orderBy('created_at')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    public function listByWaiter(string $waiterId): array
    {
        return DB::table('kasbons')
            ->where('waiter_id', $waiterId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    public function getStats(): array
    {
        $totalActive = (int) DB::table('kasbons')->where('status', 'active')->sum('remaining');
        $countActive = DB::table('kasbons')->where('status', 'active')->distinct('waiter_id')->count('waiter_id');
        $paidOffThisMonth = (int) DB::table('kasbons')
            ->where('status', 'paid_off')
            ->whereMonth('paid_off_at', now()->month)
            ->whereYear('paid_off_at', now()->year)
            ->sum('amount');

        return [
            'total_active_amount' => $totalActive,
            'count_active_waiters' => $countActive,
            'paid_off_this_month' => $paidOffThisMonth,
        ];
    }

    // =========================================================================
    //  WAITER SETTINGS (Firebase)
    // =========================================================================

    public function getWaiterKasbonSettings(string $waiterId): array
    {
        $waiter = $this->firebase->getWaiterById($waiterId) ?: [];

        return [
            'kasbon_enabled' => (bool) ($waiter['kasbon_enabled'] ?? false),
            'kasbon_limit_percent' => isset($waiter['kasbon_limit_percent']) ? (int) $waiter['kasbon_limit_percent'] : null,
        ];
    }

    public function updateWaiterKasbonSettings(string $waiterId, array $patch): void
    {
        $payload = [];
        if (array_key_exists('kasbon_enabled', $patch)) {
            $payload['kasbon_enabled'] = (bool) $patch['kasbon_enabled'];
        }
        if (array_key_exists('kasbon_limit_percent', $patch)) {
            $val = $patch['kasbon_limit_percent'];
            $payload['kasbon_limit_percent'] = ($val !== null && $val !== '') ? max(0, min(100, (int) $val)) : null;
        }
        if (! empty($payload)) {
            $payload['updated_at'] = time();
            $this->database->getReference('allowed_waiters/' . $waiterId)->update($payload);
        }
    }

    // =========================================================================
    //  NOTIFICATIONS
    // =========================================================================

    protected function notifySupervisorKasbonCreated(array $waiter, int $amount, string $reason): void
    {
        $config = $this->payroll->getConfig();
        $phone = trim((string) ($config['supervisor_phone'] ?? ''));
        if ($phone === '') return;

        $name = (string) ($waiter['name'] ?? 'Karyawan');
        $msg = "💰 Kasbon Dicairkan\n\n";
        $msg .= "Karyawan: {$name}\n";
        $msg .= "Nominal: Rp " . number_format($amount, 0, ',', '.') . "\n";
        if ($reason !== '') {
            $msg .= "Alasan: {$reason}\n";
        }
        $msg .= "\nDicairkan oleh Finance.";

        try {
            $this->fonnte?->sendMessage($phone, $msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifySupervisorKasbonPaidOff(object $kasbon): void
    {
        $config = $this->payroll->getConfig();
        $phone = trim((string) ($config['supervisor_phone'] ?? ''));
        if ($phone === '') return;

        $msg = "✅ Kasbon Lunas\n\n";
        $msg .= "Karyawan: {$kasbon->waiter_name}\n";
        $msg .= "Nominal: Rp " . number_format((int) $kasbon->amount, 0, ',', '.') . "\n";
        $msg .= "Status: Lunas otomatis dari potongan gaji.";

        try {
            $this->fonnte?->sendMessage($phone, $msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifySupervisorKasbonWrittenOff(object $kasbon, string $reason): void
    {
        $config = $this->payroll->getConfig();
        $phone = trim((string) ($config['supervisor_phone'] ?? ''));
        if ($phone === '') return;

        $msg = "⚠️ Kasbon Write-Off\n\n";
        $msg .= "Karyawan: {$kasbon->waiter_name}\n";
        $msg .= "Sisa hutang: Rp " . number_format((int) $kasbon->remaining, 0, ',', '.') . "\n";
        $msg .= "Alasan: {$reason}";

        try {
            $this->fonnte?->sendMessage($phone, $msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // =========================================================================
    //  HELPERS
    // =========================================================================

    protected function triggerWaiterFlag(string $waiterId): void
    {
        try {
            $this->database->getReference('payroll_flags/' . $waiterId)->set([
                'updated_at' => time(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
