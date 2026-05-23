<?php

namespace App\Console\Commands;

use App\Models\AiProductEnrichmentBatch;
use App\Models\AiProductEnrichmentJob;
use App\Services\FirebaseService;
use App\Services\ProductEnrichmentService;
use App\Services\ProductKnowledgeService;
use App\Services\ProductVectorSyncService;
use App\Services\SupabaseVectorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * AiProductEnrichmentBatchRun
 *
 * Jalan di background process. Eksekusi batch enrichment dengan:
 *   - Tracking progress real-time ke ai_product_enrichment_batches
 *   - Heartbeat tiap iterasi (untuk detect stuck process)
 *   - Auto-approve flag (skip review, langsung approved)
 *   - Auto-sync flag (langsung sync ke Supabase setelah approve)
 *   - Cancellation: cek status batch tiap iterasi, stop jika 'cancelled'
 *
 * Contoh internal:
 *   php artisan ai:product-enrichment:batch-run --batch-id=5 --auto-approve --auto-sync
 */
class AiProductEnrichmentBatchRun extends Command
{
    protected $signature = 'ai:product-enrichment:batch-run
                            {--batch-id= : Required - ID batch row di tabel ai_product_enrichment_batches}
                            {--auto-approve : Setelah enrichment, langsung approve knowledge}
                            {--auto-sync : Setelah approve, langsung sync ke vector store}
                            {--limit=20 : Jumlah produk per batch (override batch.options)}
                            {--only-missing : Skip produk yang sudah punya knowledge}
                            {--category-id= : Filter by category ID Firebase}';

    protected $description = '[Internal] Worker untuk batch enrichment yang dipanggil BatchProcessService.';

    public function __construct(
        protected FirebaseService $firebase,
        protected ProductKnowledgeService $knowledge,
        protected ProductEnrichmentService $enrichment,
        protected ProductVectorSyncService $vectorSync,
        protected SupabaseVectorService $vectors,
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
            $this->info('Batch sudah dibatalkan sebelum mulai.');

            return self::SUCCESS;
        }

        $autoApprove = (bool) $this->option('auto-approve') || $batch->auto_approve;
        $autoSync = (bool) $this->option('auto-sync') || $batch->auto_sync;
        $limit = (int) $this->option('limit') ?: ($batch->options['limit'] ?? 20);
        $onlyMissing = (bool) $this->option('only-missing') || ! empty($batch->options['only_missing']);
        $categoryId = (string) ($this->option('category-id') ?: ($batch->options['category_id'] ?? ''));

        $batch->update([
            'status' => 'running',
            'started_at' => now(),
            'heartbeat_at' => now(),
            'last_message' => 'Memuat daftar produk dari Firebase...',
        ]);

        try {
            $allProducts = $this->firebase->getActiveProducts();
        } catch (\Throwable $e) {
            $this->markFailed($batch, 'Gagal load produk Firebase: '.$e->getMessage());

            return self::FAILURE;
        }

        // Bangun candidate list
        $candidates = [];
        foreach ($allProducts as $p) {
            $pid = (string) ($p['id'] ?? '');
            if ($pid === '') {
                continue;
            }
            if ($categoryId !== '' && ($p['category_id'] ?? '') !== $categoryId) {
                continue;
            }
            if ($onlyMissing && $this->knowledge->exists($pid)) {
                continue;
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
                'last_message' => 'Tidak ada produk yang perlu diproses.',
                'summary' => ['note' => 'no candidates'],
            ]);

            return self::SUCCESS;
        }

        $admin = $batch->initiated_by ?: 'batch';

        foreach ($candidates as $idx => $product) {
            // Cek cancellation
            $batch->refresh();
            if ($batch->status === 'cancelled') {
                $this->info('Cancelled by user at item '.($idx + 1));
                break;
            }

            $productId = (string) ($product['id'] ?? '');
            $productName = (string) ($product['name'] ?? '');

            $batch->update([
                'current_product_id' => $productId,
                'current_product_name' => $productName,
                'last_message' => sprintf('Enrich %d/%d: %s', $idx + 1, count($candidates), $productName),
                'heartbeat_at' => now(),
            ]);

            try {
                $result = $this->enrichment->enrichProduct($product, $admin, $allProducts);

                if (($result['success'] ?? false) === true) {
                    // Auto-approve?
                    if ($autoApprove && ! empty($result['job_id'])) {
                        $apr = $this->enrichment->approveJob((int) $result['job_id'], $admin);
                        if ($apr['success'] ?? false) {
                            $batch->increment('success_count');
                            // Auto-sync vector?
                            if ($autoSync) {
                                $this->vectorSync->syncProductId($productId);
                            }
                        } else {
                            $batch->increment('failed_count');
                        }
                    } else {
                        $batch->increment('success_count');
                    }
                } else {
                    $batch->increment('failed_count');
                }
            } catch (\Throwable $e) {
                Log::error('Batch enrichment exception', [
                    'batch_id' => $batch->id,
                    'product' => $productId,
                    'msg' => $e->getMessage(),
                ]);
                $batch->increment('failed_count');
            }

            $batch->increment('processed_items');
            $batch->update(['heartbeat_at' => now()]);
        }

        // Final state
        $batch->refresh();
        if ($batch->status === 'cancelled') {
            $batch->update([
                'finished_at' => now(),
                'summary' => $this->summary($batch),
            ]);
        } else {
            $batch->update([
                'status' => 'completed',
                'finished_at' => now(),
                'last_message' => sprintf(
                    'Selesai. Sukses=%d, Gagal=%d.',
                    $batch->success_count,
                    $batch->failed_count
                ),
                'current_product_id' => null,
                'current_product_name' => null,
                'summary' => $this->summary($batch),
            ]);
        }

        return self::SUCCESS;
    }

    protected function summary(AiProductEnrichmentBatch $batch): array
    {
        return [
            'total' => $batch->total_items,
            'processed' => $batch->processed_items,
            'success' => $batch->success_count,
            'failed' => $batch->failed_count,
            'skipped' => $batch->skipped_count,
            'jobs_in_db' => AiProductEnrichmentJob::count(),
        ];
    }

    protected function markFailed(AiProductEnrichmentBatch $batch, string $msg): void
    {
        $batch->update([
            'status' => 'failed',
            'finished_at' => now(),
            'last_message' => $msg,
        ]);
        $this->error($msg);
    }
}
