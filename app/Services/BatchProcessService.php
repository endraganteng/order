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

        $command = $this->buildArtisanCommand($batch);
        $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;
        $pid = $this->spawnDetached($command);

        if ($pid !== null) {
            $batch->update(['pid' => $pid]);
        } elseif (! $isWindows) {
            // Linux/Mac: PID null = spawn benar-benar gagal.
            $batch->update([
                'status' => 'failed',
                'last_message' => 'Gagal spawn background process. Cek server logs.',
                'finished_at' => now(),
            ]);
            Log::error('BatchProcess: failed to spawn', ['batch_id' => $batch->id, 'cmd' => $command]);
        }
        // Windows: PID null adalah normal (cmd /C start /B tidak mudah expose PID).
        // Worker akan update status sendiri via handle().

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
        if ($batch->auto_approve) {
            $args[] = '--auto-approve';
        }
        if ($batch->auto_sync) {
            $args[] = '--auto-sync';
        }
        $opt = $batch->options ?? [];
        if (! empty($opt['limit'])) {
            $args[] = '--limit='.(int) $opt['limit'];
        }
        if (! empty($opt['only_missing'])) {
            $args[] = '--only-missing';
        }
        if (! empty($opt['category_id'])) {
            $args[] = '--category-id='.escapeshellarg($opt['category_id']);
        }

        return sprintf('%s %s %s %s', escapeshellarg($phpBin), escapeshellarg($artisan), $cmd, implode(' ', $args));
    }

    /**
     * Spawn process di background, return PID kalau bisa diketahui.
     * Tidak block parent.
     */
    protected function spawnDetached(string $command): ?int
    {
        $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;
        $logPath = storage_path('logs/ai-batch-'.date('Y-m-d').'.log');

        try {
            if ($isWindows) {
                // Windows: pakai popen di mode 'r' untuk start child detached.
                // Cara reliable: pakai 'start /B' via shell.
                $full = sprintf('cmd /C start /B "" %s >> %s 2>&1', $command, escapeshellarg($logPath));
                $proc = popen($full, 'r');
                if ($proc !== false) {
                    pclose($proc);
                }
                // Windows tidak gampang ambil PID dari shell start. Return null tapi proses jalan.
                return null;
            }

            // Linux/Mac: nohup ... &
            $full = sprintf('nohup %s >> %s 2>&1 & echo $!', $command, escapeshellarg($logPath));
            $output = shell_exec($full);
            $pid = $output ? (int) trim($output) : null;

            return $pid > 0 ? $pid : null;
        } catch (\Throwable $e) {
            Log::error('BatchProcess spawn exception: '.$e->getMessage());

            return null;
        }
    }

    protected function resolvePhpBinary(): string
    {
        // Coba env PHP_BINARY (paling reliable di CLI), fallback 'php' di PATH.
        if (defined('PHP_BINARY') && PHP_BINARY !== '') {
            return PHP_BINARY;
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
