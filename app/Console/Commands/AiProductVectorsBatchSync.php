<?php

namespace App\Console\Commands;

use App\Models\AiProductEnrichmentBatch;
use App\Services\FirebaseService;
use App\Services\ProductKnowledgeService;
use App\Services\ProductVectorSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * AiProductVectorsBatchSync
 *
 * [Internal] Worker background untuk sync vector massal ke Supabase.
 * Bisa dipanggil dari BatchProcessService dengan --batch-id, atau langsung manual.
 */
class AiProductVectorsBatchSync extends Command
{
    protected $signature = 'ai:product-vectors:batch-sync
                            {--batch-id= : Required - ID batch row di tabel ai_product_enrichment_batches}
                            {--limit=500 : Maksimum produk per run}
                            {--include-all : Sync semua produk aktif (juga yang belum punya knowledge)}';

    protected $description = '[Internal] Worker untuk batch vector sync.';

    public function __construct(
        protected FirebaseService $firebase,
        protected ProductKnowledgeService $knowledge,
        protected ProductVectorSyncService $sync,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $batchId = (int) $this->option('batch-id');
        if ($batchId <= 0) {
            $this->error('--batch-id wajib.');

            return self::FAILURE;
        }

        $batch = AiProductEnrichmentBatch::find($batchId);
        if (! $batch) {
            $this->error("Batch #{$batchId} tidak ditemukan.");

            return self::FAILURE;
        }
        if ($batch->status === 'cancelled') {
            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit') ?: ($batch->options['limit'] ?? 500);
        $includeAll = (bool) $this->option('include-all') || ! empty($batch->options['include_all']);

        $batch->update([
            'status' => 'running',
            'started_at' => now(),
            'heartbeat_at' => now(),
            'last_message' => 'Memuat daftar produk...',
        ]);

        try {
            $allProducts = $this->firebase->getActiveProducts();
        } catch (\Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'finished_at' => now(),
                'last_message' => 'Gagal load produk: '.$e->getMessage(),
            ]);

            return self::FAILURE;
        }

        // Build candidates: hanya yang knowledge approved (kecuali --include-all)
        $candidates = [];
        foreach ($allProducts as $p) {
            $pid = (string) ($p['id'] ?? '');
            if ($pid === '') {
                continue;
            }
            if (! $includeAll) {
                $k = $this->knowledge->get($pid);
                if (! $k || ($k['status'] ?? '') !== 'approved') {
                    continue;
                }
            }
            $candidates[] = $p;
            if (count($candidates) >= $limit) {
                break;
            }
        }

        $batch->update([
            'total_items' => count($candidates),
            'last_message' => 'Memproses '.count($candidates).' produk...',
            'heartbeat_at' => now(),
        ]);

        if (count($candidates) === 0) {
            $batch->update([
                'status' => 'completed',
                'finished_at' => now(),
                'last_message' => 'Tidak ada produk approved untuk di-sync.',
                'summary' => ['note' => 'no candidates'],
            ]);

            return self::SUCCESS;
        }

        foreach ($candidates as $idx => $product) {
            $batch->refresh();
            if ($batch->status === 'cancelled') {
                $this->info('Cancelled at item '.($idx + 1));
                break;
            }

            $pid = (string) ($product['id'] ?? '');
            $name = (string) ($product['name'] ?? '');

            $batch->update([
                'current_product_id' => $pid,
                'current_product_name' => $name,
                'last_message' => sprintf('Sync %d/%d: %s', $idx + 1, count($candidates), $name),
                'heartbeat_at' => now(),
            ]);

            try {
                $r = $this->sync->syncProduct($product);
                $status = $r['status'] ?? '?';
                if (in_array($status, ['synced'], true)) {
                    $batch->increment('success_count');
                } elseif (in_array($status, ['skipped_not_approved', 'deleted_inactive'], true)) {
                    $batch->increment('skipped_count');
                } else {
                    $batch->increment('failed_count');
                }
            } catch (\Throwable $e) {
                Log::error('Batch vector sync exception', ['batch_id' => $batch->id, 'product' => $pid, 'msg' => $e->getMessage()]);
                $batch->increment('failed_count');
            }

            $batch->increment('processed_items');
            $batch->update(['heartbeat_at' => now()]);
        }

        $batch->refresh();
        $batch->update([
            'status' => $batch->status === 'cancelled' ? 'cancelled' : 'completed',
            'finished_at' => now(),
            'last_message' => sprintf(
                'Selesai. Synced=%d, Skipped=%d, Failed=%d.',
                $batch->success_count,
                $batch->skipped_count,
                $batch->failed_count
            ),
            'current_product_id' => null,
            'current_product_name' => null,
            'summary' => [
                'total' => $batch->total_items,
                'synced' => $batch->success_count,
                'skipped' => $batch->skipped_count,
                'failed' => $batch->failed_count,
            ],
        ]);

        return self::SUCCESS;
    }
}
