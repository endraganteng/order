<?php

namespace App\Services;

use App\Models\AiProductEnrichmentBatch;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * BatchProcessService
 *
 * Spawn detached background process untuk batch enrichment / vector sync.
 * Cross-platform (Windows pakai start /B, Linux pakai nohup &).
 *
 * Background command dijalankan via artisan command yang menerima --batch-id.
 * Progress di-track melalui heartbeat + counter di tabel ai_product_enrichment_batches.
 */
class BatchProcessService
{
    /**
     * Buat batch row + spawn detached process.
     *
     * @param  array{mode: string, total_items: int, auto_approve: bool, auto_sync: bool, options: array, initiated_by: string}  $config
     */
    public function startBatch(array $config): AiProductEnrichmentBatch
    {
        $batch = AiProductEnrichmentBatch::create([
            'mode' => $config['mode'],
            'status' => 'queued',
            'total_items' => (int) ($config['total_items'] ?? 0),
            'auto_approve' => (bool) ($config['auto_approve'] ?? false),
            'auto_sync' => (bool) ($config['auto_sync'] ?? false),
            'options' => $config['options'] ?? [],
            'initiated_by' => $config['initiated_by'] ?? null,
            'heartbeat_at' => now(),
        ]);

        $logFile = storage_path('logs/ai-batch-'.$batch->id.'-'.date('Ymd-His').'.log');
        $command = $this->buildArtisanCommand($batch);
        $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;

        // Save command + log path SEBELUM spawn supaya bisa di-debug nanti
        $batch->update([
            'log_file' => $logFile,
            'artisan_command' => $command,
        ]);

        $spawn = $this->spawnDetached($command, $logFile);

        if ($spawn['success']) {
            $batch->update(['pid' => $spawn['pid']]);
        } else {
            // Spawn benar-benar gagal (Windows: popen returned false; Linux: PID 0/negatif)
            $errorMsg = $spawn['error'] ?? 'Spawn gagal tanpa error spesifik. Cek '.$logFile.' kalau ada.';
            $batch->update([
                'status' => 'failed',
                'last_message' => 'Gagal spawn background process. '.$errorMsg,
                'spawn_error' => $errorMsg,
                'finished_at' => now(),
            ]);
            Log::error('BatchProcess: failed to spawn', [
                'batch_id' => $batch->id,
                'cmd' => $command,
                'error' => $errorMsg,
                'log_file' => $logFile,
            ]);
        }

        return $batch;
    }

    /**
     * Build full artisan command sesuai mode.
     */
    protected function buildArtisanCommand(AiProductEnrichmentBatch $batch): string
    {
        $phpBin = $this->resolvePhpBinary();
        $artisan = base_path('artisan');

        $cmd = match ($batch->mode) {
            'enrichment' => 'ai:product-enrichment:batch-run',
            'vector_sync' => 'ai:product-vectors:batch-sync',
            'full_pipeline' => 'ai:product-enrichment:batch-run', // dengan auto-sync flag
            default => null,
        };
        if (! $cmd) {
            throw new \InvalidArgumentException("Unknown batch mode: {$batch->mode}");
        }

        $args = ['--batch-id='.$batch->id];
        $opt = $batch->options ?? [];

        if (in_array($batch->mode, ['enrichment', 'full_pipeline'], true)) {
            // Enrichment-specific options
            if ($batch->auto_approve) {
                $args[] = '--auto-approve';
            }
            if ($batch->auto_sync) {
                $args[] = '--auto-sync';
            }
            if (! empty($opt['only_missing'])) {
                $args[] = '--only-missing';
            }
            if (! empty($opt['category_id'])) {
                $args[] = '--category-id='.escapeshellarg($opt['category_id']);
            }
            if (! empty($opt['limit'])) {
                $args[] = '--limit='.(int) $opt['limit'];
            }
        } elseif ($batch->mode === 'vector_sync') {
            // Vector sync only supports: --limit, --include-all
            if (! empty($opt['limit'])) {
                $args[] = '--limit='.(int) $opt['limit'];
            }
            if (! empty($opt['include_all'])) {
                $args[] = '--include-all';
            }
        }

        return sprintf('%s %s %s %s', escapeshellarg($phpBin), escapeshellarg($artisan), $cmd, implode(' ', $args));
    }

    /**
     * Spawn process di background.
     * Cross-platform: Windows pakai `cmd /C start /B`, Linux pakai `nohup ... &`.
     *
     * @return array{success: bool, pid: ?int, error: ?string}
     */
    protected function spawnDetached(string $command, string $logPath): array
    {
        $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;

        // Pre-flight: pastikan dependencies tersedia
        $disabledFns = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        if ($isWindows) {
            if (! function_exists('popen') || in_array('popen', $disabledFns, true)) {
                return ['success' => false, 'pid' => null, 'error' => 'popen() disabled di php.ini. Tidak bisa spawn process di Windows.'];
            }
        } else {
            if (! function_exists('shell_exec') || in_array('shell_exec', $disabledFns, true)) {
                return ['success' => false, 'pid' => null, 'error' => 'shell_exec() disabled di php.ini. Tidak bisa spawn nohup process di Linux. Hapus shell_exec dari disable_functions atau gunakan systemd/supervisor.'];
            }
        }

        // Pastikan directory log writable
        $logDir = dirname($logPath);
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        if (! is_writable($logDir)) {
            return ['success' => false, 'pid' => null, 'error' => 'Direktori log tidak writable: '.$logDir];
        }

        // Tulis header marker ke log SUPAYA bisa verifikasi spawn berhasil mulai
        @file_put_contents($logPath, sprintf("[%s] === SPAWN START ===\nCommand: %s\nOS: %s\n", date('Y-m-d H:i:s'), $command, PHP_OS), FILE_APPEND);

        try {
            if ($isWindows) {
                $full = sprintf('cmd /C start /B "" %s >> %s 2>&1', $command, escapeshellarg($logPath));
                $proc = popen($full, 'r');
                if ($proc === false) {
                    return ['success' => false, 'pid' => null, 'error' => 'popen() returned false. Cek izin akses cmd.exe.'];
                }
                pclose($proc);
                // Windows tidak gampang ambil PID. Anggap success kalau popen tidak error.
                return ['success' => true, 'pid' => null, 'error' => null];
            }

            // Linux/Mac
            $full = sprintf('nohup %s >> %s 2>&1 & echo $!', $command, escapeshellarg($logPath));
            $output = shell_exec($full);
            $pid = $output ? (int) trim($output) : 0;
            if ($pid <= 0) {
                return ['success' => false, 'pid' => null, 'error' => 'shell_exec() returned no PID. Output: '.json_encode($output)];
            }

            return ['success' => true, 'pid' => $pid, 'error' => null];
        } catch (\Throwable $e) {
            Log::error('BatchProcess spawn exception: '.$e->getMessage());

            return ['success' => false, 'pid' => null, 'error' => 'Exception: '.$e->getMessage()];
        }
    }

    protected function resolvePhpBinary(): string
    {
        // PHP_BINARY di FPM context mengarah ke php-fpm, bukan php CLI.
        // Selalu gunakan /usr/local/bin/php jika ada, fallback ke 'php' di PATH.
        if (is_executable('/usr/local/bin/php')) {
            return '/usr/local/bin/php';
        }

        // Fallback: cari php di PATH
        $which = trim((string) shell_exec('which php 2>/dev/null'));
        if ($which !== '' && is_executable($which) && !str_contains($which, 'fpm')) {
            return $which;
        }

        return 'php';
    }

    /**
     * Cancel batch — set status, biarkan worker detect lewat polling.
     */
    public function cancelBatch(int $batchId): bool
    {
        $batch = AiProductEnrichmentBatch::find($batchId);
        if (! $batch) {
            return false;
        }
        if (in_array($batch->status, ['completed', 'failed', 'cancelled'], true)) {
            return false;
        }
        $batch->update([
            'status' => 'cancelled',
            'finished_at' => now(),
            'last_message' => 'Dibatalkan oleh user.',
        ]);

        return true;
    }
}
