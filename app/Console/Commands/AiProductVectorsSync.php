<?php

namespace App\Console\Commands;

use App\Services\FirebaseService;
use App\Services\ProductKnowledgeService;
use App\Services\ProductVectorSyncService;
use Illuminate\Console\Command;

/**
 * AiProductVectorsSync
 *
 * Tier 4 — Sync embedding vector ke Supabase pgvector.
 * Hanya produk dengan knowledge approved yang di-upsert.
 * Produk in-active → vector di-hapus.
 *
 * Contoh:
 *   php artisan ai:product-vectors:sync
 *   php artisan ai:product-vectors:sync --limit=100
 *   php artisan ai:product-vectors:sync --product=PROD_ID
 *   php artisan ai:product-vectors:sync --only-approved
 */
class AiProductVectorsSync extends Command
{
    protected $signature = 'ai:product-vectors:sync
                            {--limit=500 : Maksimal jumlah produk per run}
                            {--product= : Sync produk tertentu by ID}
                            {--only-approved : Hanya proses produk dengan knowledge.status=approved (default)}
                            {--include-all : Sync semua produk aktif (juga yang belum punya knowledge)}
                            {--dry-run : Hanya tampilkan plan, tidak panggil Gemini/Supabase}';

    protected $description = 'Sync vector embedding produk ke Supabase pgvector (massal/CLI)';

    public function __construct(
        protected FirebaseService $firebase,
        protected ProductKnowledgeService $knowledge,
        protected ProductVectorSyncService $sync,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $singleId = trim((string) $this->option('product'));
        $includeAll = (bool) $this->option('include-all');
        $dryRun = (bool) $this->option('dry-run');

        // Single mode
        if ($singleId !== '') {
            $r = $this->sync->syncProductId($singleId);
            $this->line(sprintf(' [%s] %s — %s', $r['status'], $singleId, $r['message']));

            return $r['success'] ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Mengambil daftar produk aktif...');
        $allProducts = $this->firebase->getActiveProducts();

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

        if (count($candidates) === 0) {
            $this->warn('Tidak ada produk approved untuk di-sync. (pakai --include-all untuk indeks semua aktif)');

            return self::SUCCESS;
        }

        $this->info('Akan sync '.count($candidates).' produk.');
        if ($dryRun) {
            foreach ($candidates as $p) {
                $this->line(' · '.$p['name'].' (id='.$p['id'].')');
            }
            $this->warn('DRY RUN — tidak panggil Gemini/Supabase.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($candidates));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->start();

        $summary = $this->sync->syncMany($candidates, function ($pid, $r, $idx, $total) use ($bar) {
            $bar->setMessage(($r['status'] ?? '?').': '.mb_substr($pid, 0, 30));
            $bar->advance();
        });
        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            'Selesai. Total=%d, Synced=%d, Skipped=%d, Deleted=%d, Failed=%d',
            $summary['total'],
            $summary['synced'],
            $summary['skipped'],
            $summary['deleted'],
            $summary['failed']
        ));

        if ($summary['failed'] > 0) {
            $this->warn('Ada produk yang gagal sync. Cek log Laravel untuk detail.');
        }

        return self::SUCCESS;
    }
}
