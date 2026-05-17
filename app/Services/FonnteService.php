<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function isEnabled(): bool
    {
        $settings = $this->firebase->getSettings();

        return ! empty($settings['fonnte_enabled']) && ! empty($settings['fonnte_api_token']);
    }

    /**
     * Normalize Indonesian phone numbers to Fonnte-compatible format.
     * - Strip non-digits (spaces, dashes, plus, parens).
     * - Convert leading 0 to 62 (mis. 0812... -> 62812...).
     * - Leave already-prefixed 62... as is.
     * - Empty input returns empty string.
     */
    public function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($phone === '') return '';
        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        }
        if (str_starts_with($phone, '62')) {
            return $phone;
        }
        // Asumsikan number tanpa prefix country code adalah ID local (mis. 812...) → tambah 62.
        return '62' . $phone;
    }

    public function sendMessage(string $phone, string $message): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            Log::warning('Fonnte send aborted: empty phone after normalization');
            return null;
        }

        $settings = $this->firebase->getSettings();
        $token = $settings['fonnte_api_token'];

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->asForm()->post('https://api.fonnte.com/send', [
                'target' => $phone,
                'message' => $message,
                'countryCode' => '62',
                'delay' => '1-3',
            ]);

            $result = $response->json();

            if (! ($result['status'] ?? false)) {
                Log::warning('Fonnte send failed', ['phone' => $phone, 'reason' => $result['reason'] ?? 'unknown']);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Fonnte exception: '.$e->getMessage());

            return null;
        }
    }

    public function notifyTaskAssigned(array $waiter, array $taskData): void
    {
        $phone = $waiter['phone'] ?? null;
        if (! $phone) {
            return;
        }

        $taskTitle = $taskData['title'] ?? 'Tugas baru';
        $taskType = $taskData['task_type'] ?? 'general';
        $priority = $taskData['priority'] ?? 'normal';
        $scheduledDate = $taskData['scheduled_for_date'] ?? date('Y-m-d');
        $scheduledTime = $taskData['scheduled_time'] ?? '';

        $emoji = $taskType === 'rack_check' ? "\xF0\x9F\x94\x8D" : "\xF0\x9F\x93\x8B";
        $priorityLabel = match ($priority) {
            'urgent' => "\xF0\x9F\x94\xB4 URGENT",
            'high' => "\xF0\x9F\x9F\xA0 Tinggi",
            default => "\xF0\x9F\x9F\xA2 Normal",
        };

        $message = "{$emoji} *TUGAS BARU*\n\n";
        $message .= "\xF0\x9F\x93\x8C {$taskTitle}\n";
        $message .= "\xE2\x9A\xA1 Prioritas: {$priorityLabel}\n";
        $message .= "\xF0\x9F\x93\x85 Tanggal: {$scheduledDate}\n";
        if ($scheduledTime) {
            $message .= "\xF0\x9F\x95\x90 Jam: {$scheduledTime}\n";
        }
        $message .= "\nSegera buka portal tugas untuk mengerjakannya.";

        $this->sendMessage($phone, $message);
    }

    public function notifyTaskOverdue(array $waiter, array $task): void
    {
        $phone = $waiter['phone'] ?? null;
        if (! $phone) {
            return;
        }

        $taskTitle = $task['title'] ?? 'Tugas';

        $message = "\xF0\x9F\x9A\xA8 *TUGAS OVERDUE*\n\n";
        $message .= "\xF0\x9F\x93\x8C {$taskTitle}\n";
        $message .= "\xE2\x9A\xA0\xEF\xB8\x8F Tugas ini sudah melewati batas waktu!\n\n";
        $message .= "Segera selesaikan atau hubungi supervisor.";

        $this->sendMessage($phone, $message);
    }

    /**
     * Send periodic reminders for incomplete tasks.
     * - General tasks: every 1 hour after shift start
     * - Rack check tasks: every 2 hours after clock-in
     *
     * Uses Firebase-backed durable cooldown state to avoid spamming.
     */
    public function sendTaskReminders(string $waiterId, array $pendingTasks, ?array $attendance, ?string $date = null): array
    {
        $date ??= date('Y-m-d');
        $now = time();

        if (! $this->isEnabled() || empty($pendingTasks)) {
            return ['sent' => []];
        }

        $waiter = $this->firebase->getWaiterById($waiterId);
        $phone = $waiter['phone'] ?? null;
        if (! $phone) {
            return ['sent' => []];
        }

        $clockInTs = $this->resolveClockInTimestamp($attendance, $date);

        if (! $clockInTs) {
            return ['sent' => []];
        }

        $hoursSinceClockIn = ($now - $clockInTs) / 3600;

        $generalPending = [];
        $rackPending = [];

        foreach ($pendingTasks as $task) {
            $status = $task['status'] ?? 'pending';
            if ($status !== 'pending') {
                continue;
            }

            $taskType = $task['task_type'] ?? 'general';
            if ($taskType === 'rack_check') {
                $rackPending[] = $task;
            } else {
                $generalPending[] = $task;
            }
        }

        $sent = [];

        if (! empty($generalPending) && $hoursSinceClockIn >= 1) {
            $count = count($generalPending);
            $taskNames = array_slice(array_map(fn ($task) => $task['title'] ?? 'Tugas', $generalPending), 0, 3);
            $list = implode("\n", array_map(fn ($name) => "  \xE2\x80\xA2 {$name}", $taskNames));
            if ($count > 3) {
                $list .= "\n  ... dan ".($count - 3).' tugas lainnya';
            }

            $message = "\xE2\x8F\xB0 *PENGINGAT TUGAS*\n\n";
            $message .= "Kamu masih punya *{$count} tugas umum* yang belum selesai:\n\n";
            $message .= $list."\n\n";
            $message .= 'Segera kerjakan ya!';

            if ($this->dispatchReminder($waiterId, $date, 'general', 3600, $phone, $message, [
                'pending_count' => $count,
            ], $now, 2)) {
                $sent[] = 'general';
            }
        }

        if (! empty($rackPending) && $hoursSinceClockIn >= 2) {
            $count = count($rackPending);
            $rackNames = array_slice(array_map(fn ($task) => $task['rack_name'] ?? $task['title'] ?? 'Rak', $rackPending), 0, 3);
            $list = implode("\n", array_map(fn ($name) => "  \xE2\x80\xA2 {$name}", $rackNames));
            if ($count > 3) {
                $list .= "\n  ... dan ".($count - 3).' rak lainnya';
            }

            $message = "\xF0\x9F\x94\x8D *PENGINGAT CEK RAK*\n\n";
            $message .= "Kamu masih punya *{$count} rak* yang belum dicek:\n\n";
            $message .= $list."\n\n";
            $message .= 'Jangan lupa scan QR dan isi checklist produk!';

            if ($this->dispatchReminder($waiterId, $date, 'rack_check', 7200, $phone, $message, [
                'pending_count' => $count,
            ], $now, 2)) {
                $sent[] = 'rack_check';
            }
        }

        return ['sent' => $sent];
    }

    /**
     * Send weekly task summary report to supervisor.
     */
    public function sendWeeklyReport(array $stats): bool
    {
        $phone = $this->getReportPhone();
        if (! $phone || ! $this->isAutoReportEnabled()) {
            return false;
        }

        $period = $stats['period'] ?? 'Minggu ini';
        $totalTasks = (int) ($stats['total'] ?? 0);
        $doneTasks = (int) ($stats['done'] ?? 0);
        $overdueTasks = (int) ($stats['overdue'] ?? 0);
        $completionRate = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100) : 0;

        $message = "\xF0\x9F\x93\x8A *LAPORAN MINGGUAN*\n";
        $message .= "Periode: {$period}\n\n";
        $message .= "\xF0\x9F\x93\x8B Total Tugas: {$totalTasks}\n";
        $message .= "\xE2\x9C\x85 Selesai: {$doneTasks}\n";
        $message .= "\xF0\x9F\x9A\xA8 Terlambat: {$overdueTasks}\n";
        $message .= "\xF0\x9F\x93\x88 Tingkat Penyelesaian: {$completionRate}%\n";

        if (! empty($stats['top_performers'])) {
            $message .= "\n\xF0\x9F\x8F\x86 *Top Performer:*\n";
            foreach (array_slice($stats['top_performers'], 0, 3) as $i => $perf) {
                $rank = $i + 1;
                $message .= "  {$rank}. {$perf['name']} ({$perf['done']}/{$perf['total']})\n";
            }
        }

        if (! empty($stats['needs_attention'])) {
            $message .= "\n\xE2\x9A\xA0\xEF\xB8\x8F *Perlu Perhatian:*\n";
            foreach (array_slice($stats['needs_attention'], 0, 3) as $waiter) {
                $message .= "  \xE2\x80\xA2 {$waiter['name']} ({$waiter['done']}/{$waiter['total']})\n";
            }
        }

        $result = $this->sendMessage($phone, $message);

        return is_array($result) && ($result['status'] ?? false);
    }

    /**
     * Send monthly task summary report to supervisor.
     */
    public function sendMonthlyReport(array $stats): bool
    {
        $phone = $this->getReportPhone();
        if (! $phone || ! $this->isAutoReportEnabled()) {
            return false;
        }

        $period = $stats['period'] ?? 'Bulan ini';
        $totalTasks = (int) ($stats['total'] ?? 0);
        $doneTasks = (int) ($stats['done'] ?? 0);
        $overdueTasks = (int) ($stats['overdue'] ?? 0);
        $completionRate = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100) : 0;
        $totalWaiters = (int) ($stats['total_waiters'] ?? 0);

        $message = "\xF0\x9F\x93\x85 *LAPORAN BULANAN*\n";
        $message .= "Periode: {$period}\n\n";
        $message .= "\xF0\x9F\x91\xA5 Waiter Aktif: {$totalWaiters}\n";
        $message .= "\xF0\x9F\x93\x8B Total Tugas: {$totalTasks}\n";
        $message .= "\xE2\x9C\x85 Selesai: {$doneTasks}\n";
        $message .= "\xF0\x9F\x9A\xA8 Terlambat: {$overdueTasks}\n";
        $message .= "\xF0\x9F\x93\x88 Tingkat Penyelesaian: {$completionRate}%\n";

        if (! empty($stats['by_category'])) {
            $message .= "\n\xF0\x9F\x93\x82 *Per Kategori:*\n";
            foreach (array_slice($stats['by_category'], 0, 5) as $cat) {
                $catRate = $cat['total'] > 0 ? round(($cat['done'] / $cat['total']) * 100) : 0;
                $message .= "  \xE2\x80\xA2 {$cat['name']}: {$cat['done']}/{$cat['total']} ({$catRate}%)\n";
            }
        }

        if (! empty($stats['top_performers'])) {
            $message .= "\n\xF0\x9F\x8F\x86 *Top 3 Performer:*\n";
            foreach (array_slice($stats['top_performers'], 0, 3) as $i => $perf) {
                $rank = $i + 1;
                $perfRate = $perf['total'] > 0 ? round(($perf['done'] / $perf['total']) * 100) : 0;
                $message .= "  {$rank}. {$perf['name']} \u2014 {$perfRate}% ({$perf['done']}/{$perf['total']})\n";
            }
        }

        $result = $this->sendMessage($phone, $message);

        return is_array($result) && ($result['status'] ?? false);
    }

    /**
     * Get the supervisor phone number for reports.
     */
    protected function getReportPhone(): ?string
    {
        $settings = $this->firebase->getSettings();
        $phone = trim((string) ($settings['report_phone'] ?? ''));

        return $phone !== '' ? $phone : null;
    }

    /**
     * Check if auto-report is enabled.
     */
    protected function isAutoReportEnabled(): bool
    {
        $settings = $this->firebase->getSettings();

        return ! empty($settings['auto_report_enabled']) && $this->isEnabled();
    }

    /**
     * Send stock shortage alert to supervisor when rack check finds low/out items.
     */
    public function notifyStockShortage(string $rackName, string $waiterName, array $shortageItems, ?array $productChecklist = null): bool
    {
        $phone = $this->getReportPhone();
        if (! $phone || ! $this->isEnabled()) {
            return false;
        }

        $message = "\xE2\x9A\xA0\xEF\xB8\x8F *ALERT STOK HABIS/RENDAH*\n\n";
        $message .= "\xF0\x9F\x93\x8D Rak: {$rackName}\n";
        $message .= "\xF0\x9F\x91\xA4 Dicek oleh: {$waiterName}\n";
        $message .= "\xF0\x9F\x95\x90 Waktu: ".date('d/m/Y H:i')."\n\n";

        if (! empty($productChecklist)) {
            $message .= "*Produk bermasalah:*\n";
            foreach ($productChecklist as $item) {
                if (! empty($item['is_shortage'])) {
                    $name = $item['product_name'] ?? '-';
                    $actual = $item['actual_qty'] ?? 0;
                    $standard = $item['standard_qty'] ?? 0;
                    $unit = $item['product_unit'] ?? 'pcs';
                    $message .= "• {$name}: {$actual}/{$standard} {$unit}\n";
                }
            }
        } elseif (! empty($shortageItems)) {
            $message .= "*Barang habis/menipis:*\n";
            foreach (array_slice($shortageItems, 0, 15) as $item) {
                $itemName = is_array($item) ? ($item['name'] ?? $item[0] ?? '-') : $item;
                $message .= "• {$itemName}\n";
            }
            if (count($shortageItems) > 15) {
                $message .= "... dan ".(count($shortageItems) - 15)." item lainnya\n";
            }
        }

        $message .= "\nSegera lakukan restock.";

        $result = $this->sendMessage($phone, $message);

        return is_array($result) && ($result['status'] ?? false);
    }

    /**
     * Notify supervisor saat task recurring di-reschedule karena waiter libur.
     *
     * @param array $template       Template data (title, rack_name, etc.)
     * @param array $waiterOriginal Waiter assignee asli yang libur
     * @param array $waiterNew      Waiter baru yang akan kerjakan (sama orang, beda hari)
     * @param string $originalDate  Tanggal cycle asli (Y-m-d)
     * @param string $newDate       Tanggal task akan dijalankan (Y-m-d)
     */
    public function notifyTaskRescheduled(array $template, array $waiterOriginal, array $waiterNew, string $originalDate, string $newDate): bool
    {
        $phone = $this->getReportPhone();
        if (! $phone || ! $this->isEnabled()) {
            return false;
        }

        $taskTitle = (string) ($template['title'] ?? 'Tugas');
        $rackName = (string) ($template['rack_name'] ?? '');
        $waiterOrigName = (string) ($waiterOriginal['name'] ?? 'Waiter');
        $waiterNewName = (string) ($waiterNew['name'] ?? 'Waiter');

        $message = "📅 *Task Direschedule*\n\n";
        $message .= "Task: {$taskTitle}";
        if ($rackName !== '') {
            $message .= " ({$rackName})";
        }
        $message .= "\n";
        $message .= "Tanggal asli: {$originalDate}\n";
        $message .= "Tanggal baru: {$newDate}\n";
        $message .= "Alasan: Waiter {$waiterOrigName} libur di tanggal asli.\n";

        if ($waiterOrigName === $waiterNewName) {
            $message .= 'Waiter sama akan kerjakan di hari masuk berikutnya.';
        } else {
            $message .= "Akan dikerjakan oleh: {$waiterNewName}";
        }

        $result = $this->sendMessage($phone, $message);

        return is_array($result) && ($result['status'] ?? false);
    }

    /**
     * Notify supervisor URGENT saat task tidak bisa direschedule
     * (semua waiter libur > 7 hari atau load semua sudah penuh).
     *
     * @param  array  $template       Template data
     * @param  string $originalDate   Tanggal cycle asli (Y-m-d)
     * @param  int    $daysSearched   Berapa hari ke depan dicari (default 7)
     */
    public function notifyTaskUrgentNoCoverage(array $template, string $originalDate, int $daysSearched = 7): bool
    {
        $phone = $this->getReportPhone();
        if (! $phone || ! $this->isEnabled()) {
            return false;
        }

        $taskTitle = (string) ($template['title'] ?? 'Tugas');
        $rackName = (string) ($template['rack_name'] ?? '');

        $message = "🚨 *URGENT: Task Tidak Bisa Dijalankan*\n\n";
        $message .= "Task: {$taskTitle}";
        if ($rackName !== '') {
            $message .= " ({$rackName})";
        }
        $message .= "\n";
        $message .= "Tanggal asli: {$originalDate}\n";
        $message .= "Tidak ada waiter masuk dalam {$daysSearched} hari ke depan,\n";
        $message .= "atau load semua waiter sudah penuh (>=5 task/hari).\n\n";
        $message .= "Aksi yang disarankan:\n";
        $message .= "• Assign manual ke waiter lain via panel admin\n";
        $message .= "• Atau skip cycle ini, tunggu cycle berikutnya";

        $result = $this->sendMessage($phone, $message);

        return is_array($result) && ($result['status'] ?? false);
    }

    protected function resolveClockInTimestamp(?array $attendance, string $date): ?int
    {
        if (! $attendance) {
            return null;
        }

        $timestamp = (int) ($attendance['clock_in_timestamp'] ?? 0);
        if ($timestamp > 0) {
            return $timestamp;
        }

        $clockIn = trim((string) ($attendance['clock_in'] ?? ''));
        if ($clockIn === '') {
            return null;
        }

        $normalized = preg_match('/^\d{2}:\d{2}$/', $clockIn) ? $clockIn.':00' : $clockIn;
        $resolved = strtotime($date.' '.$normalized);

        return $resolved !== false ? $resolved : null;
    }

    protected function dispatchReminder(string $waiterId, string $date, string $type, int $cooldownSeconds, string $phone, string $message, array $metadata, int $now, int $maxSends = 0): bool
    {
        $claimed = $this->firebase->claimTaskReminderDispatch($waiterId, $date, $type, $cooldownSeconds, $now, $maxSends);
        if (! $claimed) {
            return false;
        }

        try {
            $result = $this->sendMessage($phone, $message);
            if (! is_array($result) || ! ($result['status'] ?? false)) {
                $this->firebase->releaseTaskReminderDispatch($waiterId, $date, $type, $now);

                return false;
            }

            $this->firebase->completeTaskReminderDispatch($waiterId, $date, $type, $now, $metadata);

            return true;
        } catch (\Throwable $e) {
            $this->firebase->releaseTaskReminderDispatch($waiterId, $date, $type, $now);
            report($e);

            return false;
        }
    }
}
