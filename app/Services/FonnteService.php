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
     * Uses session-based cooldown to avoid spamming.
     */
    public function sendTaskReminders(string $waiterId, array $pendingTasks, ?array $attendance): void
    {
        if (!$this->isEnabled() || empty($pendingTasks)) {
            return;
        }

        $waiter = $this->firebase->getWaiterById($waiterId);
        $phone = $waiter['phone'] ?? null;
        if (!$phone) {
            return;
        }

        // Determine clock-in time
        $clockInTs = null;
        if ($attendance && !empty($attendance['clock_in'])) {
            $clockInTs = (int) $attendance['clock_in'];
        }

        // If not clocked in yet, no reminders
        if (!$clockInTs) {
            return;
        }

        $now = time();
        $hoursSinceClockIn = ($now - $clockInTs) / 3600;

        // Get last reminder timestamps from session
        $lastReminders = session('fonnte_task_reminders', []);

        $generalPending = [];
        $rackPending = [];

        foreach ($pendingTasks as $task) {
            $status = $task['status'] ?? 'pending';
            if ($status !== 'pending') continue;

            $taskType = $task['task_type'] ?? 'general';
            if ($taskType === 'rack_check') {
                $rackPending[] = $task;
            } else {
                $generalPending[] = $task;
            }
        }

        // General tasks: remind every 1 hour
        if (!empty($generalPending)) {
            $lastGeneralReminder = (int) ($lastReminders['general'] ?? 0);
            $hoursSinceLastReminder = $lastGeneralReminder > 0 ? ($now - $lastGeneralReminder) / 3600 : 999;

            // Send if: at least 1 hour since clock-in AND at least 1 hour since last reminder
            if ($hoursSinceClockIn >= 1 && $hoursSinceLastReminder >= 1) {
                $count = count($generalPending);
                $taskNames = array_slice(array_map(fn($t) => $t['title'] ?? 'Tugas', $generalPending), 0, 3);
                $list = implode("\n", array_map(fn($n) => "  \xE2\x80\xA2 {$n}", $taskNames));
                if ($count > 3) {
                    $list .= "\n  ... dan " . ($count - 3) . " tugas lainnya";
                }

                $message = "\xE2\x8F\xB0 *PENGINGAT TUGAS*\n\n";
                $message .= "Kamu masih punya *{$count} tugas umum* yang belum selesai:\n\n";
                $message .= $list . "\n\n";
                $message .= "Segera kerjakan ya!";

                $this->sendMessage($phone, $message);
                $lastReminders['general'] = $now;
            }
        }

        // Rack check tasks: remind every 2 hours
        if (!empty($rackPending)) {
            $lastRackReminder = (int) ($lastReminders['rack_check'] ?? 0);
            $hoursSinceLastReminder = $lastRackReminder > 0 ? ($now - $lastRackReminder) / 3600 : 999;

            // Send if: at least 2 hours since clock-in AND at least 2 hours since last reminder
            if ($hoursSinceClockIn >= 2 && $hoursSinceLastReminder >= 2) {
                $count = count($rackPending);
                $rackNames = array_slice(array_map(fn($t) => $t['rack_name'] ?? $t['title'] ?? 'Rak', $rackPending), 0, 3);
                $list = implode("\n", array_map(fn($n) => "  \xE2\x80\xA2 {$n}", $rackNames));
                if ($count > 3) {
                    $list .= "\n  ... dan " . ($count - 3) . " rak lainnya";
                }

                $message = "\xF0\x9F\x94\x8D *PENGINGAT CEK RAK*\n\n";
                $message .= "Kamu masih punya *{$count} rak* yang belum dicek:\n\n";
                $message .= $list . "\n\n";
                $message .= "Jangan lupa scan QR dan isi checklist produk!";

                $this->sendMessage($phone, $message);
                $lastReminders['rack_check'] = $now;
            }
        }

        // Save updated timestamps to session
        session()->put('fonnte_task_reminders', $lastReminders);
    }
}
