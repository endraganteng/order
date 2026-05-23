<?php

namespace App\Console\Commands;

use App\Models\AiProductEnrichmentJob;
use App\Services\FirebaseService;
use App\Services\ProductEnrichmentService;
use App\Services\ProductKnowledgeService;
use Illuminate\Console\Command;

/**
 * AiProductEnrichmentGenerate
 *
 * Tier 3 (Massal) — Artisan command untuk enrich banyak produk dari CLI.
 * Tidak ada queue worker. Jalan sequential dengan progress bar + resume.
 *
 * Contoh:
 *   php artisan ai:product-enrichment:generate --limit=20
 *   php artisan ai:product-enrichment:generate --resume
 *   php artisan ai:product-enrichment:generate --product=PROD_ID
 */
class AiProductEnrichmentGenerate extends Command
{
    protected $signature = 'ai:product-enrichment:generate
                            {--limit=20 : Maksimal jumlah produk yang di-enrich di run ini}
                            {--product= : Enrich produk tertentu by ID (override --limit)}
                            {--resume : Lanjutkan dari produk yang belum punya knowledge (skip yang sudah)}
                            {--include-existing : Re-enrich produk yang sudah punya knowledge}
                            {--dry-run : Hanya tampilkan kandidat, tidak panggil Gemini}';

    protected $description = 'Enrich knowledge produk via Gemini grounded search (massal/CLI)';

    public function __construct(
        protected FirebaseService $firebase,
        protected ProductKnowledgeService $knowledge,
        protected ProductEnrichmentService $enrichment,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $singleProductId = trim((string) $this->option('product'));
        $resume = (bool) $this->option('resume');
        $includeExisting = (bool) $this->option('include-existing');
        $dryRun = (bool) $this->option('dry-run');

        // Single-produk mode
        if ($singleProductId !== '') {
            $product = $this->firebase->getProductById($singleProductId);
            if (! $product) {
                $this->error("Produk {$singleProductId} tidak ditemukan.");

                return self::FAILURE;
            }
            $this->info("Enrich single produk: {$product['name']} ({$singleProductId})");
            if ($dryRun) {
                $this->warn('DRY RUN — tidak panggil Gemini.');

                return self::SUCCESS;
            }
            $r = $this->enrichment->enrichByProductId($singleProductId, 'cli');
            $this->printResult($product, $r);

            return $r['success'] ? self::SUCCESS : self::FAILURE;
        }

        // Massal mode
        $this->info('Mengambil daftar produk aktif dari Firebase...');
        $allProducts = $this->firebase->getActiveProducts();
        $this->info('Total produk aktif: '.count($allProducts));

        $candidates = [];
        foreach ($allProducts as $p) {
            $pid = (string) ($p['id'] ?? '');
            if ($pid === '') {
                continue;
            }

            $exists = $this->knowledge->exists($pid);
            if ($resume && $exists) {
                continue;
            }
            if (! $includeExisting && $exists) {
                continue;
            }

            $candidates[] = $p;
            if (count($candidates) >= $limit) {
                break;
            }
        }

        if (count($candidates) === 0) {
            $this->info('Tidak ada produk yang perlu di-enrich. (gunakan --include-existing untuk re-enrich)');

            return self::SUCCESS;
        }

        $this->info('Akan memproses '.count($candidates).' produk.');
        if ($dryRun) {
            foreach ($candidates as $p) {
                $this->line(sprintf(' · %s (id=%s)', $p['name'] ?? '-', $p['id'] ?? '-'));
            }
            $this->warn('DRY RUN — tidak panggil Gemini.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($candidates));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('mulai');
        $bar->start();

        $stats = ['success' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($candidates as $product) {
            $bar->setMessage(mb_substr((string) ($product['name'] ?? ''), 0, 40));
            try {
                $r = $this->enrichment->enrichProduct($product, 'cli', $allProducts);
                if (($r['success'] ?? false) === true) {
                    $stats['success']++;
                } else {
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                $this->newLine();
                $this->error('Exception: '.$e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Selesai. Sukses=%d, Gagal=%d, Skip=%d. Total job di MySQL: %d.',
            $stats['success'],
            $stats['failed'],
            $stats['skipped'],
            AiProductEnrichmentJob::count()
        ));

        return self::SUCCESS;
    }

    protected function printResult(array $product, array $r): void
    {
        $status = $r['status'] ?? 'unknown';
        $msg = $r['message'] ?? '-';
        $jobId = $r['job_id'] ?? '-';
        if ($r['success'] ?? false) {
            $this->info("✓ {$product['name']}: status={$status}, job=#{$jobId}, {$msg}");
        } else {
            $this->error("✗ {$product['name']}: status={$status}, {$msg}");
        }
    }
}
