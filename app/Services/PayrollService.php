<?php

namespace App\Services;

use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Exception\Database\TransactionFailed;

class PayrollService
{
    protected FirebaseService $firebase;

    protected Database $database;

    protected ?FonnteService $fonnte;

    public function __construct(FirebaseService $firebase, Database $database, ?FonnteService $fonnte = null)
    {
        $this->firebase = $firebase;
        $this->database = $database;
        $this->fonnte = $fonnte ?? app(FonnteService::class);
    }

    // =========================================================================
    //  CONFIG
    // =========================================================================

    /**
     * Get payroll global config (supervisor phone, future settings).
     */
    public function getConfig(): array
    {
        $snapshot = $this->database->getReference('payroll_config')->getSnapshot();
        $config = $snapshot->exists() ? (array) $snapshot->getValue() : [];

        return array_merge([
            'supervisor_phone' => '',
            'public_base_url' => '',
            'is_active' => true,
        ], $config);
    }

    public function updateConfig(array $patch): void
    {
        $allowed = ['supervisor_phone', 'public_base_url', 'is_active'];
        $clean = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $patch)) {
                $clean[$key] = $patch[$key];
            }
        }
        if (! empty($clean)) {
            $this->database->getReference('payroll_config')->update($clean);
        }
    }

    /**
     * Resolve a clean public base URL from config (no trailing slash).
     * Returns '' if not configured — caller harus fallback ke teks deskriptif.
     */
    protected function getPublicBaseUrl(): string
    {
        $url = trim((string) ($this->getConfig()['public_base_url'] ?? ''));
        if ($url === '') return '';
        return rtrim($url, '/');
    }

    // =========================================================================
    //  WAITER SETTINGS (per-orang)
    // =========================================================================

    /**
     * Read payroll-related fields from a waiter record.
     */
    public function getWaiterSettings(string $waiterId): array
    {
        $waiter = $this->firebase->getWaiterById($waiterId) ?: [];

        return [
            'payroll_enabled'        => (bool) ($waiter['payroll_enabled'] ?? false),
            'monthly_salary'         => (int) ($waiter['monthly_salary'] ?? 0),
            'payday'                 => (int) ($waiter['payday'] ?? 0),
            'bank_name'              => (string) ($waiter['bank_name'] ?? ''),
            'bank_account_number'    => (string) ($waiter['bank_account_number'] ?? ''),
            'bank_account_holder'    => (string) ($waiter['bank_account_holder'] ?? ''),
        ];
    }

    /**
     * Update payroll-related fields on a waiter record.
     */
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
        $value = $this->database->getReference('waiter_payroll_balance/' . $waiterId . '/balance')->getValue();
        return (int) ($value ?? 0);
    }

    /**
     * Atomically adjust balance via runTransaction. Returns the new balance.
     */
    public function adjustBalance(string $waiterId, int $delta): int
    {
        $ref = $this->database->getReference('waiter_payroll_balance/' . $waiterId);

        $newBalance = 0;
        $this->database->runTransaction(function ($transaction) use (&$newBalance, $ref, $delta) {
            $snap = $transaction->snapshot($ref);
            $current = $snap->exists() ? (array) $snap->getValue() : [];
            $balance = (int) ($current['balance'] ?? 0) + $delta;
            $newBalance = $balance;
            $transaction->set($ref, [
                'balance' => $balance,
                'updated_at' => time(),
            ]);
        });

        return $newBalance;
    }

    // =========================================================================
    //  TRANSACTIONS
    // =========================================================================

    /**
     * Internal: write a transaction record. Returns transaction id.
     */
    protected function writeTransaction(array $payload): string
    {
        $payload['created_at'] = time();
        $ref = $this->database->getReference('waiter_payroll_transactions')->push($payload);
        return (string) $ref->getKey();
    }

    /**
     * Idempotent credit. Returns ['tx_id' => ..., 'balance_after' => int, 'created' => bool].
     * If $idempotencyKey already exists, no-op and returns existing tx_id.
     */
    public function creditIfAbsent(string $waiterId, int $amount, string $type, string $reference, string $note, string $idempotencyKey): array
    {
        if ($amount <= 0) {
            return ['tx_id' => '', 'balance_after' => $this->getBalance($waiterId), 'created' => false, 'reason' => 'amount<=0'];
        }
        $idempRef = $this->database->getReference('waiter_payroll_idempotency/' . $idempotencyKey);
        $existing = $idempRef->getValue();
        if (is_array($existing) && ! empty($existing['tx_id'])) {
            return [
                'tx_id' => (string) $existing['tx_id'],
                'balance_after' => $this->getBalance($waiterId),
                'created' => false,
                'reason' => 'idempotent_hit',
            ];
        }

        // Reserve idempotency claim atomically (best-effort).
        $claimed = false;
        try {
            $this->database->runTransaction(function ($transaction) use (&$claimed, $idempRef, $idempotencyKey) {
                $snap = $transaction->snapshot($idempRef);
                if ($snap->exists()) {
                    return;
                }
                $transaction->set($idempRef, [
                    'reserved_at' => time(),
                    'idempotency_key' => $idempotencyKey,
                ]);
                $claimed = true;
            });
        } catch (TransactionFailed $e) {
            $claimed = false;
        }

        if (! $claimed) {
            $existing = $idempRef->getValue();
            return [
                'tx_id' => is_array($existing) ? (string) ($existing['tx_id'] ?? '') : '',
                'balance_after' => $this->getBalance($waiterId),
                'created' => false,
                'reason' => 'idempotent_race',
            ];
        }

        // Adjust balance + write tx.
        $newBalance = $this->adjustBalance($waiterId, $amount);
        $txId = $this->writeTransaction([
            'waiter_id'     => $waiterId,
            'type'          => $type,
            'amount'        => $amount,
            'balance_after' => $newBalance,
            'status'        => 'completed',
            'reference'     => $reference,
            'note'          => $note,
            'idempotency_key' => $idempotencyKey,
        ]);

        $idempRef->update(['tx_id' => $txId, 'completed_at' => time()]);

        return ['tx_id' => $txId, 'balance_after' => $newBalance, 'created' => true];
    }

    /**
     * Manual credit (THR, bonus extra, dst). Always creates a new tx, no idempotency check.
     */
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
            'reference'     => '',
            'note'          => $note,
            'created_by'    => $createdBy,
        ]);
        return ['success' => true, 'tx_id' => $txId, 'balance_after' => $newBalance];
    }

    /**
     * List transactions for a waiter (newest first), capped to $limit.
     */
    public function listTransactionsByWaiter(string $waiterId, int $limit = 100): array
    {
        $snapshot = $this->database->getReference('waiter_payroll_transactions')
            ->orderByChild('waiter_id')
            ->equalTo($waiterId)
            ->getSnapshot();

        if (! $snapshot->exists()) {
            return [];
        }

        $rows = [];
        foreach ((array) $snapshot->getValue() as $id => $tx) {
            if (! is_array($tx)) continue;
            $rows[] = array_merge(['id' => (string) $id], $tx);
        }
        usort($rows, fn ($a, $b) => ((int) ($b['created_at'] ?? 0)) <=> ((int) ($a['created_at'] ?? 0)));
        return array_slice($rows, 0, max(1, $limit));
    }

    public function getTransaction(string $txId): ?array
    {
        $snap = $this->database->getReference('waiter_payroll_transactions/' . $txId)->getSnapshot();
        if (! $snap->exists()) return null;
        $data = (array) $snap->getValue();
        return array_merge(['id' => $txId], $data);
    }

    // =========================================================================
    //  WITHDRAWALS
    // =========================================================================

    /**
     * Karyawan submit penarikan. Status 'pending', tidak potong saldo dulu.
     * Notify supervisor via WA.
     */
    public function requestWithdrawal(string $waiterId, int $amount, string $note = ''): array
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        if (! $waiter) {
            return ['success' => false, 'message' => 'Data karyawan tidak ditemukan.'];
        }
        if (empty($waiter['payroll_enabled'])) {
            return ['success' => false, 'message' => 'Akun payroll Anda belum diaktifkan oleh supervisor.'];
        }
        $amount = max(0, $amount);
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Nominal penarikan harus > 0.'];
        }
        $balance = $this->getBalance($waiterId);
        if ($amount > $balance) {
            return ['success' => false, 'message' => 'Saldo tidak cukup. Saldo Anda: Rp '.number_format($balance, 0, ',', '.')];
        }

        $bankName = (string) ($waiter['bank_name'] ?? '');
        $bankAcc = (string) ($waiter['bank_account_number'] ?? '');
        $bankHolder = (string) ($waiter['bank_account_holder'] ?? '');
        if ($bankName === '' || $bankAcc === '' || $bankHolder === '') {
            return ['success' => false, 'message' => 'Data rekening belum lengkap. Hubungi supervisor untuk melengkapi.'];
        }

        $txId = $this->writeTransaction([
            'waiter_id'             => $waiterId,
            'waiter_name'           => (string) ($waiter['name'] ?? ''),
            'type'                  => 'withdrawal',
            'amount'                => $amount,
            'balance_after'         => null,
            'status'                => 'pending',
            'reference'             => '',
            'note'                  => $note,
            'bank_name'             => $bankName,
            'bank_account_number'   => $bankAcc,
            'bank_account_holder'   => $bankHolder,
        ]);

        // Notify supervisor via WA.
        $this->notifySupervisorWithdrawalRequest($waiter, $amount, $note, $txId);

        return ['success' => true, 'tx_id' => $txId, 'message' => 'Permintaan penarikan dikirim. Menunggu approval supervisor.'];
    }

    /**
     * Supervisor approve withdrawal: deduct balance, mark approved.
     */
    public function approveWithdrawal(string $txId, string $approvedBy = 'Supervisor'): array
    {
        $tx = $this->getTransaction($txId);
        if (! $tx) return ['success' => false, 'message' => 'Transaksi tidak ditemukan.'];
        if (($tx['type'] ?? '') !== 'withdrawal') return ['success' => false, 'message' => 'Bukan transaksi penarikan.'];
        if (($tx['status'] ?? '') !== 'pending') return ['success' => false, 'message' => 'Transaksi tidak dalam status pending.'];

        $waiterId = (string) ($tx['waiter_id'] ?? '');
        $amount = (int) ($tx['amount'] ?? 0);
        $balance = $this->getBalance($waiterId);
        if ($amount > $balance) {
            return ['success' => false, 'message' => 'Saldo karyawan tidak cukup saat ini (mungkin sudah berubah). Reject saja.'];
        }

        $newBalance = $this->adjustBalance($waiterId, -$amount);

        $this->database->getReference('waiter_payroll_transactions/' . $txId)->update([
            'status'        => 'approved',
            'balance_after' => $newBalance,
            'processed_at'  => time(),
            'processed_by'  => $approvedBy,
        ]);

        $this->notifyWaiterWithdrawalApproved($waiterId, $amount, $newBalance);

        return ['success' => true, 'balance_after' => $newBalance];
    }

    public function rejectWithdrawal(string $txId, string $reason = '', string $rejectedBy = 'Supervisor'): array
    {
        $tx = $this->getTransaction($txId);
        if (! $tx) return ['success' => false, 'message' => 'Transaksi tidak ditemukan.'];
        if (($tx['type'] ?? '') !== 'withdrawal') return ['success' => false, 'message' => 'Bukan transaksi penarikan.'];
        if (($tx['status'] ?? '') !== 'pending') return ['success' => false, 'message' => 'Transaksi tidak dalam status pending.'];

        $this->database->getReference('waiter_payroll_transactions/' . $txId)->update([
            'status'        => 'rejected',
            'reject_reason' => $reason,
            'processed_at'  => time(),
            'processed_by'  => $rejectedBy,
        ]);

        $this->notifyWaiterWithdrawalRejected((string) ($tx['waiter_id'] ?? ''), (int) ($tx['amount'] ?? 0), $reason);

        return ['success' => true];
    }

    /**
     * List pending withdrawals (admin queue). Newest first.
     */
    public function listPendingWithdrawals(int $limit = 100): array
    {
        $snapshot = $this->database->getReference('waiter_payroll_transactions')
            ->orderByChild('status')
            ->equalTo('pending')
            ->getSnapshot();
        if (! $snapshot->exists()) return [];

        $rows = [];
        foreach ((array) $snapshot->getValue() as $id => $tx) {
            if (! is_array($tx)) continue;
            if (($tx['type'] ?? '') !== 'withdrawal') continue;
            $rows[] = array_merge(['id' => (string) $id], $tx);
        }
        usort($rows, fn ($a, $b) => ((int) ($a['created_at'] ?? 0)) <=> ((int) ($b['created_at'] ?? 0)));
        return array_slice($rows, 0, $limit);
    }

    // =========================================================================
    //  AUTO-CREDIT (SCHEDULER)
    // =========================================================================

    /**
     * Daily scheduler entry: process all eligible waiters whose payday is today
     * (or within catchup window of N days). Idempotent: same waiter+month
     * cannot be credited twice.
     *
     * Returns ['credited' => N, 'skipped' => N, 'errors' => [...]].
     */
    public function runDailySalaryCredit(int $catchupDays = 7): array
    {
        $today = new \DateTimeImmutable(date('Y-m-d'));
        $waiters = $this->firebase->getActiveWaiters();
        $credited = 0;
        $skipped = 0;
        $errors = [];

        foreach ($waiters as $waiter) {
            $waiterId = (string) ($waiter['id'] ?? '');
            if ($waiterId === '') continue;
            if (empty($waiter['payroll_enabled'])) {
                $skipped++;
                continue;
            }
            $payday = (int) ($waiter['payday'] ?? 0);
            $salary = (int) ($waiter['monthly_salary'] ?? 0);
            if ($payday < 1 || $payday > 28 || $salary <= 0) {
                $skipped++;
                continue;
            }

            // Check if payday <= today within catchup window.
            $year = (int) $today->format('Y');
            $month = (int) $today->format('m');
            $candidates = [];
            for ($i = 0; $i <= $catchupDays; $i++) {
                $check = $today->modify('-' . $i . ' day');
                $checkY = (int) $check->format('Y');
                $checkM = (int) $check->format('m');
                $checkD = (int) $check->format('d');
                if ($checkD === $payday) {
                    $candidates[] = sprintf('%04d-%02d', $checkY, $checkM);
                }
            }
            // Dedupe, keep most recent payday month within window.
            $candidates = array_values(array_unique($candidates));
            if (empty($candidates)) {
                $skipped++;
                continue;
            }

            foreach ($candidates as $monthRef) {
                $idempKey = $waiterId . '_salary_' . $monthRef;
                try {
                    $result = $this->creditIfAbsent(
                        $waiterId,
                        $salary,
                        'salary_credit',
                        $monthRef,
                        'Gaji pokok ' . $monthRef,
                        $idempKey
                    );
                    if (! empty($result['created'])) {
                        $credited++;
                        $this->notifyWaiterSalaryCredited($waiterId, $salary, $monthRef, $result['balance_after']);
                    }
                } catch (\Throwable $e) {
                    $errors[] = ['waiter_id' => $waiterId, 'month' => $monthRef, 'error' => $e->getMessage()];
                }
            }
        }

        return [
            'credited' => $credited,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ];
    }

    /**
     * Hook untuk dipanggil saat bonus bulanan di-finalisasi.
     * Kalau waiter payroll_enabled=true dan total_amount > 0, credit ke saldo.
     */
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
        $result = $this->creditIfAbsent(
            $waiterId,
            $totalBonusAmount,
            'bonus_credit',
            $month,
            'Bonus bulanan ' . $month,
            $idempKey
        );
        if (! empty($result['created'])) {
            $this->notifyWaiterBonusCredited($waiterId, $totalBonusAmount, $month, $result['balance_after']);
        }
        return ['success' => true] + $result;
    }

    // =========================================================================
    //  WA NOTIFICATIONS
    // =========================================================================

    protected function notifySupervisorWithdrawalRequest(array $waiter, int $amount, string $note, string $txId): void
    {
        $config = $this->getConfig();
        $phone = trim((string) ($config['supervisor_phone'] ?? ''));
        if ($phone === '') return;

        $name = (string) ($waiter['name'] ?? 'Karyawan');
        $bankName = (string) ($waiter['bank_name'] ?? '-');
        $bankAcc = (string) ($waiter['bank_account_number'] ?? '-');
        $bankHolder = (string) ($waiter['bank_account_holder'] ?? '-');
        $msg = "Permintaan Penarikan Gaji\n\n";
        $msg .= "Karyawan: {$name}\n";
        $msg .= "Nominal: Rp " . number_format($amount, 0, ',', '.') . "\n\n";
        $msg .= "Rekening tujuan:\n";
        $msg .= "Bank: {$bankName}\n";
        $msg .= "Nomor: {$bankAcc}\n";
        $msg .= "Atas Nama: {$bankHolder}\n";
        if ($note !== '') {
            $msg .= "\nCatatan: {$note}\n";
        }
        $publicUrl = $this->getPublicBaseUrl();
        if ($publicUrl !== '') {
            $msg .= "\nApprove atau Reject di:\n" . $publicUrl . "/admin/payroll/withdrawals";
        } else {
            $msg .= "\nApprove atau Reject di halaman admin payroll.";
        }

        try {
            $this->fonnte->sendMessage($phone, $msg);
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
        if ($publicUrl !== '') {
            $msg .= "Dana akan ditransfer ke rekening Anda. Cek di portal Gaji Saya:\n" . $publicUrl . "/waiter/payroll";
        } else {
            $msg .= "Dana akan ditransfer ke rekening Anda. Cek di portal Gaji Saya.";
        }

        try {
            $this->fonnte->sendMessage($phone, $msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function notifyWaiterWithdrawalRejected(string $waiterId, int $amount, string $reason): void
    {
        $waiter = $this->firebase->getWaiterById($waiterId);
        $phone = trim((string) ($waiter['phone'] ?? ''));
        if ($phone === '') return;

        $msg = "Penarikan Ditolak\n\n";
        $msg .= "Nominal: Rp " . number_format($amount, 0, ',', '.') . "\n";
        if ($reason !== '') {
            $msg .= "Alasan: {$reason}\n\n";
        }
        $msg .= "Saldo Anda tidak berubah. Hubungi supervisor jika ada pertanyaan.";

        try {
            $this->fonnte->sendMessage($phone, $msg);
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
        if ($publicUrl !== '') {
            $msg .= "Cek di portal Gaji Saya:\n" . $publicUrl . "/waiter/payroll";
        } else {
            $msg .= "Cek di portal Gaji Saya.";
        }

        try {
            $this->fonnte->sendMessage($phone, $msg);
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
        if ($publicUrl !== '') {
            $msg .= "Cek di portal Gaji Saya:\n" . $publicUrl . "/waiter/payroll";
        } else {
            $msg .= "Cek di portal Gaji Saya.";
        }

        try {
            $this->fonnte->sendMessage($phone, $msg);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
