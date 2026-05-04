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

    public function sendMessage(string $phone, string $message): ?array
    {
        if (! $this->isEnabled()) {
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
            ], $now)) {
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
            ], $now)) {
                $sent[] = 'rack_check';
            }
        }

        return ['sent' => $sent];
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

    protected function dispatchReminder(string $waiterId, string $date, string $type, int $cooldownSeconds, string $phone, string $message, array $metadata, int $now): bool
    {
        $claimed = $this->firebase->claimTaskReminderDispatch($waiterId, $date, $type, $cooldownSeconds, $now);
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
