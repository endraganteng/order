<?php

namespace App\Services;

use App\Models\AiProductEnrichmentJob;
use App\Models\AiProductEnrichmentLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProductEnrichmentService
 *
 * Orchestrator yang membungkus alur enrichment satu produk:
 *   1. Cek apakah produk PRIMARY (base) atau VARIANT.
 *   2. Jika PRIMARY: jalankan extraction grounded → simpan knowledge → buat job MySQL.
 *   3. Jika VARIANT: cari knowledge primary di group → inherit (deep-copy) →
 *      modify ukuran_varian = [variant_label] → set status `needs_review` → buat job MySQL.
 *   4. Tulis log per langkah ke ai_product_enrichment_logs.
 *
 * Service ini TIDAK memanggil sync vector (itu tugas ProductVectorSyncService).
 * Approval (admin click "Approve") yang akan trigger vector sync.
 */
class ProductEnrichmentService
{
    public function __construct(
        protected FirebaseService $firebase,
        protected ProductKnowledgeService $knowledge,
        protected ProductKnowledgeExtractionService $extractor,
        protected VariantDetectorService $variant,
    ) {
    }

    /**
     * Enrich satu produk by ID.
     *
     * @return array{success: bool, job_id?: int, status: string, message: string, knowledge?: array, is_inherited?: bool}
     */
    public function enrichByProductId(string $productId, ?string $generatedBy = null, ?array $allProductsCache = null): array
    {
        $product = $this->firebase->getProductById($productId);
        if (! $product) {
            return ['success' => false, 'status' => 'failed', 'message' => "Produk {$productId} tidak ditemukan."];
        }

        return $this->enrichProduct($product, $generatedBy, $allProductsCache);
    }

    /**
     * Enrich pakai object produk yang sudah di-fetch.
     *
     * @param  array{id: string, name: string, category_id?: string|null}  $product
     * @param  array<int, array>|null  $allProductsCache  Optional cache untuk hindari getProducts() berulang.
     */
    public function enrichProduct(array $product, ?string $generatedBy = null, ?array $allProductsCache = null): array
    {
        $productId = (string) ($product['id'] ?? '');
        $productName = trim((string) ($product['name'] ?? ''));
        if ($productId === '' || $productName === '') {
            return ['success' => false, 'status' => 'failed', 'message' => 'Produk tidak valid (id/name kosong).'];
        }

        $detected = $this->variant->detect($productName);
        $baseName = $detected['base'];
        $variantLabel = $detected['variant'];

        // Tentukan PRIMARY produk dari group (kalau punya varian saudara).
        $allProducts = $allProductsCache ?? $this->firebase->getActiveProducts();
        $groups = $this->variant->groupByBase($allProducts);
        $group = $groups[$baseName] ?? null;
        $primaryId = $group['primary_id'] ?? $productId;
        $isPrimary = ($primaryId === $productId);

        // Kalau bukan primary tapi primary belum punya knowledge approved → enrich primary dulu.
        if (! $isPrimary) {
            $primaryKnowledge = $this->knowledge->get($primaryId);
            if (! $primaryKnowledge || ($primaryKnowledge['status'] ?? '') !== 'approved') {
                // Recursive enrich primary (cache reused).
                $primaryProduct = $this->firebase->getProductById($primaryId);
                if ($primaryProduct) {
                    $this->enrichProduct($primaryProduct, $generatedBy, $allProducts);
                }
            }

            return $this->enrichAsVariant($product, $primaryId, $variantLabel, $baseName, $generatedBy);
        }

        // PRIMARY path: panggil extractor.
        return $this->enrichAsPrimary($product, $baseName, $variantLabel, $generatedBy);
    }

    /**
     * Path PRIMARY: panggil grounded extraction, simpan knowledge.
     */
    protected function enrichAsPrimary(array $product, string $baseName, ?string $variantLabel, ?string $generatedBy): array
    {
        $productId = (string) $product['id'];
        $productName = (string) $product['name'];
        $categoryName = $this->resolveCategoryName($product['category_id'] ?? null);

        $job = $this->createJob([
            'product_id' => $productId,
            'product_name' => $productName,
            'base_name' => mb_substr($baseName, 0, 191),
            'variant_label' => $variantLabel,
            'inherited_from_product_id' => null,
            'is_inherited' => false,
            'status' => 'extracting',
            'generated_by' => $generatedBy,
        ]);

        $this->log($job->id, $productId, 'extract_start', "Mulai grounded extraction untuk '{$productName}'");

        $extracted = $this->extractor->extractForProduct($productName, $categoryName);
        if (! $extracted) {
            $job->update([
                'status' => 'failed',
                'error_message' => 'Grounded extraction gagal atau JSON tidak valid.',
            ]);
            $this->log($job->id, $productId, 'extract_failed', 'Extraction returned null.');

            return ['success' => false, 'job_id' => $job->id, 'status' => 'failed', 'message' => 'Extraction gagal.'];
        }

        // Simpan knowledge ke Firebase (status pending sampai admin approve).
        $knowledge = [
            'tipe_produk' => $extracted['tipe_produk'] ?? 'general',
            'brand' => $extracted['brand'],
            'manfaat' => $extracted['manfaat'],
            'fungsi' => $extracted['fungsi'],
            'target_hewan' => $extracted['target_hewan'],
            'gejala_terkait' => $extracted['gejala_terkait'],
            'kategori_penggunaan' => $extracted['kategori_penggunaan'],
            'aturan_pakai' => $extracted['aturan_pakai'],
            'peringatan' => $extracted['peringatan'],
            'ukuran_varian' => $variantLabel ? [$variantLabel] : ($extracted['ukuran_varian'] ?? []),
            'spesifikasi' => $extracted['spesifikasi'] ?? [],
            'sources' => $extracted['sources'],
            'confidence_score' => $extracted['confidence_score'],
            'status' => 'pending',
            'is_inherited' => false,
            'inherited_from' => null,
            'review_note' => null,
        ];
        $this->knowledge->save($productId, $knowledge);

        $job->update([
            'status' => 'pending_review',
            'source_count' => count($extracted['sources']),
            'confidence_score' => $extracted['confidence_score'],
            'metadata' => [
                'search_queries' => $extracted['search_queries'],
                'category_name' => $categoryName,
            ],
        ]);
        $this->log($job->id, $productId, 'extract_done', sprintf(
            'Extraction selesai. confidence=%.2f, sources=%d.',
            $extracted['confidence_score'],
            count($extracted['sources'])
        ));

        return [
            'success' => true,
            'job_id' => $job->id,
            'status' => 'pending_review',
            'message' => 'Knowledge terbentuk (menunggu approval).',
            'knowledge' => $knowledge,
            'is_inherited' => false,
        ];
    }

    /**
     * Path VARIANT: inherit dari primary, modify ukuran_varian, status needs_review.
     */
    protected function enrichAsVariant(array $product, string $primaryId, ?string $variantLabel, string $baseName, ?string $generatedBy): array
    {
        $productId = (string) $product['id'];
        $productName = (string) $product['name'];

        $primaryKnowledge = $this->knowledge->get($primaryId);
        if (! $primaryKnowledge) {
            // Primary masih gagal extract → tandai variant gagal juga.
            $job = $this->createJob([
                'product_id' => $productId,
                'product_name' => $productName,
                'base_name' => mb_substr($baseName, 0, 191),
                'variant_label' => $variantLabel,
                'inherited_from_product_id' => $primaryId,
                'is_inherited' => true,
                'status' => 'failed',
                'generated_by' => $generatedBy,
                'error_message' => "Primary {$primaryId} belum punya knowledge.",
            ]);
            $this->log($job->id, $productId, 'inherit_failed', "Primary {$primaryId} tidak punya knowledge.");

            return [
                'success' => false,
                'job_id' => $job->id,
                'status' => 'failed',
                'message' => "Primary belum di-enrich. Coba enrich produk base '{$baseName}' dulu.",
            ];
        }

        $job = $this->createJob([
            'product_id' => $productId,
            'product_name' => $productName,
            'base_name' => mb_substr($baseName, 0, 191),
            'variant_label' => $variantLabel,
            'inherited_from_product_id' => $primaryId,
            'is_inherited' => true,
            'status' => 'extracting',
            'generated_by' => $generatedBy,
        ]);
        $this->log($job->id, $productId, 'inherit_start', "Inherit knowledge dari primary {$primaryId}");

        $inherited = $primaryKnowledge;
        $inherited['ukuran_varian'] = $variantLabel ? [$variantLabel] : ($primaryKnowledge['ukuran_varian'] ?? []);
        $inherited['status'] = 'needs_review';
        $inherited['is_inherited'] = true;
        $inherited['inherited_from'] = $primaryId;
        $inherited['review_note'] = "Otomatis di-inherit dari produk base ID {$primaryId}. Mohon verifikasi ukuran/varian dan informasi spesifik.";
        // Hapus approval lama agar tidak ke-bawa.
        unset($inherited['approved_by'], $inherited['approved_at']);

        $this->knowledge->save($productId, $inherited);

        $sourceCount = is_array($inherited['sources'] ?? null) ? count($inherited['sources']) : 0;
        $confidence = (float) ($inherited['confidence_score'] ?? 0);

        $job->update([
            'status' => 'needs_review',
            'source_count' => $sourceCount,
            'confidence_score' => $confidence,
            'metadata' => [
                'inherited_from' => $primaryId,
                'variant_label' => $variantLabel,
            ],
        ]);
        $this->log($job->id, $productId, 'inherit_done', "Inherit dari {$primaryId} selesai. status=needs_review.");

        return [
            'success' => true,
            'job_id' => $job->id,
            'status' => 'needs_review',
            'message' => "Knowledge di-inherit dari produk base. Mohon review.",
            'knowledge' => $inherited,
            'is_inherited' => true,
        ];
    }

    /**
     * Approve job (dipanggil controller setelah admin click).
     * Mengubah status di Firebase + MySQL.
     * Caller (controller) yang trigger sync vector via ProductVectorSyncService.
     */
    public function approveJob(int $jobId, string $approverIdentifier): array
    {
        $job = AiProductEnrichmentJob::find($jobId);
        if (! $job) {
            return ['success' => false, 'message' => 'Job tidak ditemukan.'];
        }
        if (! in_array($job->status, ['pending_review', 'needs_review', 'failed'], true)) {
            return ['success' => false, 'message' => "Job status '{$job->status}' tidak bisa di-approve."];
        }

        $ok = $this->knowledge->approve($job->product_id, $approverIdentifier);
        if (! $ok) {
            return ['success' => false, 'message' => 'Gagal update knowledge di Firebase.'];
        }

        $job->update([
            'status' => 'approved',
            'approved_by' => $approverIdentifier,
            'approved_at' => now(),
        ]);
        $this->log($job->id, $job->product_id, 'approved', "Approved oleh {$approverIdentifier}.", ['approver' => $approverIdentifier]);

        return ['success' => true, 'job_id' => $job->id, 'product_id' => $job->product_id, 'message' => 'Approved.'];
    }

    public function rejectJob(int $jobId, string $approverIdentifier, ?string $reason = null): array
    {
        $job = AiProductEnrichmentJob::find($jobId);
        if (! $job) {
            return ['success' => false, 'message' => 'Job tidak ditemukan.'];
        }

        $this->knowledge->reject($job->product_id, $approverIdentifier, $reason);

        $job->update([
            'status' => 'rejected',
            'approved_by' => $approverIdentifier,
            'approved_at' => now(),
            'error_message' => $reason,
        ]);
        $this->log($job->id, $job->product_id, 'rejected', "Rejected oleh {$approverIdentifier}.".($reason ? " Alasan: {$reason}" : ''));

        return ['success' => true, 'job_id' => $job->id, 'product_id' => $job->product_id, 'message' => 'Rejected.'];
    }

    /**
     * Public helper: create job row dengan default fields.
     */
    public function createJob(array $attrs): AiProductEnrichmentJob
    {
        return DB::transaction(function () use ($attrs) {
            return AiProductEnrichmentJob::create($attrs);
        });
    }

    public function log(?int $jobId, string $productId, string $action, ?string $message = null, ?array $metadata = null, ?int $userId = null): void
    {
        try {
            AiProductEnrichmentLog::create([
                'job_id' => $jobId,
                'product_id' => $productId,
                'action' => $action,
                'message' => $message,
                'metadata' => $metadata,
                'user_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Enrichment log save failed: '.$e->getMessage());
        }
    }

    protected function resolveCategoryName(?string $categoryId): ?string
    {
        if (! $categoryId) {
            return null;
        }
        $map = $this->firebase->getProductCategoriesMap();
        $cat = $map[$categoryId] ?? null;
        if (is_array($cat) && isset($cat['name'])) {
            return (string) $cat['name'];
        }

        return null;
    }
}
