<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Contract\Database;

class PayrollService
{
    protected FirebaseService $firebase;

    protected Database $database;

    protected ?FonnteService $fonnte;

    protected ?FinanceService $finance;

    public function __construct(FirebaseService $firebase, Database $database, ?FonnteService $fonnte = null, ?FinanceService $finance = null)
    {
        $this->firebase = $firebase;
        $this->database = $database;
        try {
            $this->fonnte = $fonnte ?? app(FonnteService::class);
        } catch (\Throwable $e) {
            $this->fonnte = null;
        }
        $this->finance = $finance ?? app(FinanceService::class);
    }

    // =========================================================================
    //  FIREBASE TRIGGER FLAG (real-time notif ke portal waiter)
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

    // =========================================================================
    //  CONFIG
    // =========================================================================

    public function getConfig(): array
    {
        $rows = DB::table('payroll_configs')->pluck('value', 'key')->toArray();

        return array_merge([
            'supervisor_phone' => '',
            'public_base_url' => '',
            'is_active' => '1',
        ], $rows);
    }

    public function updateConfig(array $patch): void
    {
        $allowed = ['supervisor_phone', 'public_base_url', 'is_active'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $patch)) {
                DB::table('payroll_configs')->updateOrInsert(
                    ['key' => $key],
                    ['value' => (string) $patch[$key], 'updated_at' => now()]
                );
            }
        }
    }

    protected function getPublicBaseUrl(): string
    {
        $url = trim((string) ($this->getConfig()['public_base_url'] ?? ''));
        return $url === '' ? '' : rtrim($url, '/');
    }

    // =========================================================================
    //  WAITER SETTINGS (tetap di Firebase — bagian dari data waiter)
    // =========================================================================

    public function getWaiterSettings(string $waiterId): array
    {
        $waiter = $this->firebase->getWaiterById($waiterId) ?: [];

        return [
            'payroll_enabled'     => (bool) ($waiter['payroll_enabled'] ?? false),
            'monthly_salary'      => (int) ($waiter['monthly_salary'] ?? 0),
            'payday'              => (int) ($waiter['payday'] ?? 0),
            'bank_name'           => (string) ($waiter['bank_name'] ?? ''),
            'bank_account_number' => (string) ($waiter['bank_account_number'] ?? ''),
            'bank_account_holder' => (string) ($waiter['bank_account_holder'] ?? ''),
        ];
    }

    public function updateWaiterSettings(string $waiterId, array $patch): void
    {
        $payload = [];
        if (array_key_exists('payroll_enabled', $patch)) {
            $payload['payroll_enabled'] = (bool) $patch['payroll_enabled'];
        }
        if (array_key_exists('monthly_salary', $patch)) {
            $payload['monthly_salary'] = max(0, (int) $patch['monthly_salary']);
        }
        if (array_key_exists('payday', $patch)) {
            $day = (int) $patch['payday'];
            $payload['payday'] = ($day >= 1 && $day <= 28) ? $day : 0;
        }
        foreach (['bank_name', 'bank_account_number', 'bank_account_holder'] as $key) {
            if (array_key_exists($key, $patch)) {
                $payload[$key] = trim((string) $patch[$key]);
            }
        }
        $payload['updated_at'] = time();

        $this->database->getReference('allowed_waiters/' . $waiterId)->update($payload);
    }

    // =========================================================================
    //  BALANCE
    // =========================================================================

    public function getBalance(string $waiterId): int
    {
        return (int) (DB::table('payroll_balances')->where('waiter_id', $waiterId)->value('balance') ?? 0);
    }

    /**
     * Public wrapper for KasbonService access.
     */
    public function adjustBalancePublic(string $waiterId, int $delta): int
    {
        return $this->adjustBalance($waiterId, $delta);
    }

    protected function adjustBalance(string $waiterId, int $delta): int
    {
        return DB::transaction(function () use ($waiterId, $delta) {
            $row = DB::table('payroll_balances')->where('waiter_id', $waiterId)->lockForUpdate()->first();

            if ($row) {
                $newBalance = (int) $row->balance + $delta;
                DB::table('payroll_balances')->where('id', $row->id)->update([
                    'balance' => $newBalance,
                    'updated_at' => now(),
                ]);
            } else {
                $newBalance = $delta;
                DB::table('payroll_balances')->insert([
                    'waiter_id' => $waiterId,
                    'balance' => $newBalance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $newBalance;
        });
    }

    // =========================================================================
    //  TRANSACTIONS
    // =========================================================================

    protected function writeTransaction(array $payload): int
    {
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        return DB::table('payroll_transactions')->insertGetId($payload);
    }

    public function creditIfAbsent(string $waiterId, int $amount, string $type, string $reference, string $note, string $idempotencyKey): array
    {
        if ($amount <= 0) {
            return ['tx_id' => null, 'balance_after' => $this->getBalance($waiterId), 'created' => false, 'reason' => 'amount<=0'];
        }

        // Check idempotency
        $existing = DB::table('payroll_idempotency')->where('idempotency_key', $idempotencyKey)->first();
        if ($existing && $existing->transaction_id) {
            return [
                'tx_id' => (int) $existing->transaction_id,
                'balance_after' => $this->getBalance($waiterId),
                'created' => false,
                'reason' => 'idempotent_hit',
            ];
        }

        return DB::transaction(function () use ($waiterId, $amount, $type, $reference, $note, $idempotencyKey) {
            // Double-check inside transaction with lock
            $lock = DB::table('payroll_idempotency')->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($lock && $lock->transaction_id) {
                return [
                    'tx_id' => (int) $lock->transaction_id,
                    'balance_after' => $this->getBalance($waiterId),
                    'created' => false,
                    'reason' => 'idempotent_race',
                ];
            }

            $newBalance = $this->adjustBalance($waiterId, $amount);

            $txId = $this->writeTransaction([
                'waiter_id'      => $waiterId,
                'type'           => $type,
                'amount'         => $amount,
                'balance_after'  => $newBalance,
                'status'         => 'completed',
                'reference'      => $reference,
                'note'           => $note,
                'idempotency_key' => $idempotencyKey,
            ]);

            DB::table('payroll_idempotency')->updateOrInsert(
                ['idempotency_key' => $idempotencyKey],
                ['transaction_id' => $txId, 'updated_at' => now()]
            );

            $this->triggerWaiterFlag($waiterId);

            // Auto-deduct kasbon dari credit yang baru masuk
            try {
                $kasbonService = app(KasbonService::class);
                $kasbonService->autoDeductFromCredit($waiterId, $amount);
            } catch (\Throwable $e) {
                report($e);
            }

            return ['tx_id' => $txId, 'balance_after' => $newBalance, 'created' => true];
        });
    }

    public function manualCredit(string $waiterId, int $amount, string $note, string $createdBy): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Nominal harus > 0'];
        }

        $newBalance = $this->adjustBalance($waiterId, $amount);
        $txId = $this->writeTransaction([
            'waiter_id'     => $waiterId,
            'type'          => 'manual_credit',
            'amount'        => $amount,
            'balance_after' => $newBalance,
            'status'        => 'completed',
            'note'          => $note,
            'created_by'    => $createdBy,
        ]);

        $this->triggerWaiterFlag($waiterId);

        return ['success' => true, 'tx_id' => $txId, 'balance_after' => $newBalance];
    }

    public function listTransactionsByWaiter(string $waiterId, int $limit = 100): array
    {
        return DB::table('payroll_transactions')
            ->where('waiter_id', $waiterId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    public function getTransaction(int $txId): ?array
    {
        $row = DB::table('payroll_transactions')->find($txId);
        return $row ? (array) $row : null;
    }

    // =========================================================================
    //  WITHDRAWALS
    // =========================================================================

    public function requestWithdrawal(string $waiterId, int $amount, string $note = ''): array
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        if (! $waiter) {
            return ['success' => false, 'message' => 'Data karyawan tidak ditemukan.'];
        }
        if (empty($waiter['payroll_enabled'])) {
            return ['success' => false, 'message' => 'Akun payroll Anda belum diaktifkan oleh supervisor.'];
        }
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Nominal penarikan harus > 0.'];
        }
        $balance = $this->getBalance($waiterId);
        if ($amount > $balance) {
            return ['success' => false, 'message' => 'Saldo tidak cukup. Saldo Anda: Rp ' . number_format($balance, 0, ',', '.')];
        }

        $bankName = (string) ($waiter['bank_name'] ?? '');
        $bankAcc = (string) ($waiter['bank_account_number'] ?? '');
        $bankHolder = (string) ($waiter['bank_account_holder'] ?? '');
        if ($bankName === '' || $bankAcc === '' || $bankHolder === '') {
            return ['success' => false, 'message' => 'Data rekening belum lengkap. Hubungi supervisor untuk melengkapi.'];
        }

        $approvalToken = bin2hex(random_bytes(32));

        $txId = $this->writeTransaction([
            'waiter_id'           => $waiterId,
            'waiter_name'         => (string) ($waiter['name'] ?? ''),
            'type'                => 'withdrawal',
            'amount'              => $amount,
            'status'              => 'pending',
            'note'                => $note,
            'bank_name'           => $bankName,
            'bank_account_number' => $bankAcc,
            'bank_account_holder' => $bankHolder,
            'approval_token'      => $approvalToken,
        ]);

        $this->triggerWaiterFlag($waiterId);
        $this->notifySupervisorWithdrawalRequest($waiter, $amount, $note, $txId, $approvalToken);
        $this->notifyFinanceWithdrawalRequest($waiter, $amount, $txId);

        return ['success' => true, 'tx_id' => $txId, 'message' => 'Permintaan penarikan dikirim. Menunggu approval supervisor.'];
    }

    public function approveWithdrawal(int $txId, string $approvedBy = 'Supervisor'): array
    {
        $tx = $this->getTransaction($txId);
        if (! $tx) return ['success' => false, 'message' => 'Transaksi tidak ditemukan.'];
        if (($tx['type'] ?? '') !== 'withdrawal') return ['success' => false, 'message' => 'Bukan transaksi penarikan.'];
        if (($tx['status'] ?? '') !== 'pending') return ['success' => false, 'message' => 'Transaksi tidak dalam status pending.'];

        $waiterId = (string) $tx['waiter_id'];
        $amount = (int) $tx['amount'];
        $balance = $this->getBalance($waiterId);
        if ($amount > $balance) {
            return ['success' => false, 'message' => 'Saldo karyawan tidak cukup saat ini. Reject saja.'];
        }

        $newBalance = $this->adjustBalance($waiterId, -$amount);

        DB::table('payroll_transactions')->where('id', $txId)->update([
            'status'         => 'approved',
            'balance_after'  => $newBalance,
            'processed_at'   => now(),
            'processed_by'   => $approvedBy,
            'approval_token' => null,
            'updated_at'     => now(),
        ]);

        // Integrate with finance: record cash_mutation as expense
        $this->recordWithdrawalToFinance($tx, $newBalance);

        $this->triggerWaiterFlag($waiterId);
        $this->notifyWaiterWithdrawalApproved($waiterId, $amount, $newBalance);

        return ['success' => true, 'balance_after' => $newBalance];
    }

    public function rejectWithdrawal(int $txId, string $reason = '', string $rejectedBy = 'Supervisor'): array
    {
        $tx = $this->getTransaction($txId);
        if (! $tx) return ['success' => false, 'message' => 'Transaksi tidak ditemukan.'];
        if (($tx['type'] ?? '') !== 'withdrawal') return ['success' => false, 'message' => 'Bukan transaksi penarikan.'];
        if (($tx['status'] ?? '') !== 'pending') return ['success' => false, 'message' => 'Transaksi tidak dalam status pending.'];

        DB::table('payroll_transactions')->where('id', $txId)->update([
            'status'         => 'rejected',
            'reject_reason'  => $reason,
            'processed_at'   => now(),
            'processed_by'   => $rejectedBy,
            'approval_token' => null,
            'updated_at'     => now(),
        ]);

        $this->triggerWaiterFlag((string) $tx['waiter_id']);
        $this->notifyWaiterWithdrawalRejected((string) $tx['waiter_id'], (int) $tx['amount'], $reason);

        return ['success' => true];
    }

    public function approveByToken(int $txId, string $token, string $approvedBy = 'Supervisor (via WA link)'): array
    {
        $verify = $this->verifyApprovalToken($txId, $token);
        if (! $verify['ok']) return ['success' => false, 'message' => $verify['message']];
        return $this->approveWithdrawal($txId, $approvedBy);
    }

    public function rejectByToken(int $txId, string $token, string $reason = '', string $rejectedBy = 'Supervisor (via WA link)'): array
    {
        $verify = $this->verifyApprovalToken($txId, $token);
        if (! $verify['ok']) return ['success' => false, 'message' => $verify['message']];
        return $this->rejectWithdrawal($txId, $reason, $rejectedBy);
    }

    public function verifyApprovalToken(int $txId, string $token): array
    {
        $token = trim($token);
        if ($token === '' || strlen($token) < 16) {
            return ['ok' => false, 'message' => 'Token tidak valid.', 'tx' => null];
        }
        $tx = $this->getTransaction($txId);
        if (! $tx) return ['ok' => false, 'message' => 'Transaksi tidak ditemukan.', 'tx' => null];
        if (($tx['type'] ?? '') !== 'withdrawal') return ['ok' => false, 'message' => 'Bukan transaksi penarikan.', 'tx' => $tx];
        if (($tx['status'] ?? '') !== 'pending') {
            return ['ok' => false, 'message' => 'Transaksi sudah diproses (status: ' . ($tx['status'] ?? '?') . ').', 'tx' => $tx];
        }
        $stored = (string) ($tx['approval_token'] ?? '');
        if ($stored === '' || ! hash_equals($stored, $token)) {
            return ['ok' => false, 'message' => 'Token tidak cocok atau sudah dipakai.', 'tx' => $tx];
        }
        return ['ok' => true, 'message' => 'OK', 'tx' => $tx];
    }

    public function listPendingWithdrawals(int $limit = 100): array
    {
        return DB::table('payroll_transactions')
            ->where('type', 'withdrawal')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array) $r)
            ->toArray();
    }

    // =========================================================================
    //  FINANCE INTEGRATION (cash_mutations)
    // =========================================================================

    protected function recordWithdrawalToFinance(array $tx, int $balanceAfter): void
    {
        $categoryId = $this->resolvePayrollCategory();
        if (! $categoryId) return;

        $waiterName = $tx['waiter_name'] ?? $tx['waiter_id'];
        $amount = (int) $tx['amount'];

        DB::table('cash_mutations')->insert([
            'cash_account_id'     => null,
            'finance_category_id' => $categoryId,
            'type'                => 'expense',
            'amount'              => $amount,
            'balance_after'       => 0,
            'description'         => 'Penarikan gaji: ' . $waiterName,
            'reference_type'      => 'payroll_withdrawal',
            'reference_id'        => $tx['id'],
            'transaction_date'    => now()->toDateString(),
            'transaction_time'    => now()->format('H:i:s'),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    protected function resolvePayrollCategory(): ?int
    {
        $row = DB::table('finance_api_mappings')
            ->where('mapping_type', 'category')
            ->where('api_key', 'source')
            ->where('api_value', 'payroll')
            ->where('is_active', true)
            ->first();

        return $row ? (int) $row->target_id : null;
    }

    // =========================================================================
    //  AUTO-CREDIT (SCHEDULER)
    // =========================================================================

    public function runDailySalaryCredit(int $catchupDays = 7): array
    {
        $waiters = $this->firebase->getActiveWaiters();
        return $this->processSalaryCredit($waiters, $catchupDays);
    }

    /**
     * Trigger gajian untuk waiter tertentu saja (by IDs).
     */
    public function creditSalaryForWaiters(array $waiterIds, int $catchupDays = 7): array
    {
        $waiters = array_filter(
            $this->firebase->getActiveWaiters(),
            fn($w) => in_array((string) ($w['id'] ?? ''), $waiterIds, true)
        );
        return $this->processSalaryCredit($waiters, $catchupDays);
    }

    protected function processSalaryCredit(array $waiters, int $catchupDays): array
    {
        $today = new \DateTimeImmutable(date('Y-m-d'));
        $credited = 0;
        $skipped = 0;
        $errors = [];

        foreach ($waiters as $waiter) {
            $waiterId = (string) ($waiter['id'] ?? '');
            if ($waiterId === '' || empty($waiter['payroll_enabled'])) {
                $skipped++;
                continue;
            }
            $payday = (int) ($waiter['payday'] ?? 0);
            $salary = (int) ($waiter['monthly_salary'] ?? 0);
            if ($payday < 1 || $payday > 28 || $salary <= 0) {
                $skipped++;
                continue;
            }

            $candidates = [];
            for ($i = 0; $i <= $catchupDays; $i++) {
                $check = $today->modify('-' . $i . ' day');
                if ((int) $check->format('d') === $payday) {
                    $candidates[] = $check->format('Y-m');
                }
            }
            $candidates = array_values(array_unique($candidates));
            if (empty($candidates)) {
                $skipped++;
                continue;
            }

            foreach ($candidates as $monthRef) {
                $idempKey = $waiterId . '_salary_' . $monthRef;
                try {
                    $result = $this->creditIfAbsent($waiterId, $salary, 'salary_credit', $monthRef, 'Gaji pokok ' . $monthRef, $idempKey);
                    if (! empty($result['created'])) {
                        $credited++;
                        $this->notifyWaiterSalaryCredited($waiterId, $salary, $monthRef, $result['balance_after']);
                    }
                } catch (\Throwable $e) {
                    $errors[] = ['waiter_id' => $waiterId, 'month' => $monthRef, 'error' => $e->getMessage()];
                }
            }
        }

        return ['credited' => $credited, 'skipped' => $skipped, 'errors' => $errors];
    }

    public function creditMonthlyBonusIfEligible(string $waiterId, string $month, int $totalBonusAmount): array
    {
        if ($totalBonusAmount <= 0) {
            return ['success' => false, 'reason' => 'amount<=0'];
        }
        $waiter = $this->firebase->getWaiterById($waiterId);
        if (! $waiter || empty($waiter['payroll_enabled'])) {
            return ['success' => false, 'reason' => 'not_enabled'];
        }
        $idempKey = $waiterId . '_bonus_' . $month;
        $result = $this->creditIfAbsent($waiterId, $totalBonusAmount, 'bonus_credit', $month, 'Bonus bulanan ' . $month, $idempKey);
        if (! empty($result['created'])) {
            $this->notifyWaiterBonusCredited($waiterId, $totalBonusAmount, $month, $result['balance_after']);
        }
        return ['success' => true] + $result;
    }

    // =========================================================================
    //  WA NOTIFICATIONS
    // =========================================================================

    protected function notifySupervisorWithdrawalRequest(array $waiter, int $amount, string $note, int $txId, string $approvalToken = ''): void
    {
        $name = (string) ($waiter['name'] ?? 'Karyawan');
        $bankName = (string) ($waiter['bank_name'] ?? '-');
        $bankAcc = (string) ($waiter['bank_account_number'] ?? '-');
        $bankHolder = (string) ($waiter['bank_account_holder'] ?? '-');
        $msg = "💸 *Permintaan Penarikan Gaji*\n\n";
        $msg .= "Karyawan: {$name}\n";
        $msg .= "Nominal: Rp " . number_format($amount, 0, ',', '.') . "\n\n";
        $msg .= "Rekening tujuan:\nBank: {$bankName}\nNomor: {$bankAcc}\nAtas Nama: {$bankHolder}\n";
        if ($note !== '') {
            $msg .= "\nCatatan: {$note}\n";
        }
        $publicUrl = $this->getPublicBaseUrl();
        if ($publicUrl !== '' && $approvalToken !== '') {
            $msg .= "\nProses penarikan via link berikut (tanpa login):\n";
            $msg .= $publicUrl . '/payroll/proses/' . $txId . '/' . $approvalToken;
        } elseif ($publicUrl !== '') {
            $msg .= "\nApprove atau Reject di:\n" . $publicUrl . "/admin/payroll/penarikan";
        } else {
            $msg .= "\nApprove atau Reject di halaman admin payroll.";
        }

        try {
            app(TelegramService::class)->sendToFinance($msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifyWaiterWithdrawalApproved(string $waiterId, int $amount, int $balanceAfter): void
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        $phone = trim((string) ($waiter['phone'] ?? ''));
        if ($phone === '') return;

        $msg = "Penarikan Disetujui\n\n";
        $msg .= "Nominal: Rp " . number_format($amount, 0, ',', '.') . "\n";
        $msg .= "Saldo akhir: Rp " . number_format($balanceAfter, 0, ',', '.') . "\n\n";
        $publicUrl = $this->getPublicBaseUrl();
        $msg .= $publicUrl !== ''
            ? "Dana akan ditransfer ke rekening Anda. Cek di portal Gaji Saya:\n" . $publicUrl . "/waiter/payroll"
            : "Dana akan ditransfer ke rekening Anda. Cek di portal Gaji Saya.";

        try {
            $this->fonnte?->sendMessage($phone, $msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifyWaiterWithdrawalRejected(string $waiterId, int $amount, string $reason): void
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        $phone = trim((string) ($waiter['phone'] ?? ''));
        if ($phone === '') return;

        $msg = "Penarikan Ditolak\n\nNominal: Rp " . number_format($amount, 0, ',', '.') . "\n";
        if ($reason !== '') $msg .= "Alasan: {$reason}\n\n";
        $msg .= "Saldo Anda tidak berubah. Hubungi supervisor jika ada pertanyaan.";

        try {
            $this->fonnte?->sendMessage($phone, $msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifyWaiterSalaryCredited(string $waiterId, int $amount, string $month, int $balanceAfter): void
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        $phone = trim((string) ($waiter['phone'] ?? ''));
        if ($phone === '') return;

        $msg = "Gaji Pokok Bulan {$month} Masuk\n\n";
        $msg .= "Nominal: Rp " . number_format($amount, 0, ',', '.') . "\n";
        $msg .= "Saldo Anda: Rp " . number_format($balanceAfter, 0, ',', '.') . "\n\n";
        $publicUrl = $this->getPublicBaseUrl();
        $msg .= $publicUrl !== '' ? "Cek di portal Gaji Saya:\n" . $publicUrl . "/waiter/payroll" : "Cek di portal Gaji Saya.";

        try {
            $this->fonnte?->sendMessage($phone, $msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifyWaiterBonusCredited(string $waiterId, int $amount, string $month, int $balanceAfter): void
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        $phone = trim((string) ($waiter['phone'] ?? ''));
        if ($phone === '') return;

        $msg = "Bonus Bulan {$month} Masuk\n\n";
        $msg .= "Nominal: Rp " . number_format($amount, 0, ',', '.') . "\n";
        $msg .= "Saldo Anda: Rp " . number_format($balanceAfter, 0, ',', '.') . "\n\n";
        $publicUrl = $this->getPublicBaseUrl();
        $msg .= $publicUrl !== '' ? "Cek di portal Gaji Saya:\n" . $publicUrl . "/waiter/payroll" : "Cek di portal Gaji Saya.";

        try {
            $this->fonnte?->sendMessage($phone, $msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifyFinanceWithdrawalRequest(array $waiter, int $amount, int $txId): void
    {
        $name = (string) ($waiter['name'] ?? 'Karyawan');
        $msg = "📋 *Penarikan Gaji Baru*\n\n";
        $msg .= "Karyawan: {$name}\n";
        $msg .= "Nominal: Rp " . number_format($amount, 0, ',', '.') . "\n\n";
        $publicUrl = $this->getPublicBaseUrl();
        $msg .= $publicUrl !== ''
            ? "Cek di panel admin:\n" . $publicUrl . "/admin/payroll/withdrawals"
            : "Cek di panel admin payroll.";

        try {
            app(TelegramService::class)->sendToFinance($msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
