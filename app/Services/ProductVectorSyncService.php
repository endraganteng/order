<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ProductVectorSyncService
 *
 * Bertanggung jawab atas:
 *   1. Membangun "embedding content" (plain text) dari produk + knowledge.
 *   2. Memanggil GeminiService::embed() untuk dapat vector 768 dim.
 *   3. Memanggil SupabaseVectorService::upsert() untuk simpan vector.
 *   4. Sync banyak produk berurutan (untuk artisan command).
 *
 * Hanya knowledge dengan status `approved` yang di-sync (sesuai konstrain user:
 * waiter & admin chat hanya boleh dapat answer dari knowledge yg sudah verified).
 */
class ProductVectorSyncService
{
    public function __construct(
        protected FirebaseService $firebase,
        protected ProductKnowledgeService $knowledge,
        protected GeminiService $gemini,
        protected SupabaseVectorService $vectors,
    ) {
    }

    /**
     * Sync vector satu produk.
     *
     * @return array{success: bool, status: string, message: string, product_id: string}
     */
    public function syncProductId(string $productId): array
    {
        $product = $this->firebase->getProductById($productId);
        if (! $product) {
            return ['success' => false, 'status' => 'not_found', 'message' => 'Produk tidak ditemukan.', 'product_id' => $productId];
        }

        return $this->syncProduct($product);
    }

    /**
     * Sync vector pakai product object.
     */
    public function syncProduct(array $product): array
    {
        $productId = (string) ($product['id'] ?? '');
        if ($productId === '') {
            return ['success' => false, 'status' => 'invalid', 'message' => 'product.id kosong.', 'product_id' => ''];
        }

        $isActive = $product['is_active'] ?? true;
        if ($isActive === false) {
            // Produk in-active → hapus vector kalau ada.
            $this->vectors->delete($productId);

            return ['success' => true, 'status' => 'deleted_inactive', 'message' => 'Produk in-active, vector dihapus.', 'product_id' => $productId];
        }

        $knowledge = $this->knowledge->get($productId);
        if (! $knowledge) {
            // Belum ada knowledge → tetap index produk dengan minimal info (nama+kategori).
            $knowledge = null;
        } elseif (($knowledge['status'] ?? '') !== 'approved') {
            // Knowledge ada tapi belum approved → SKIP. Pastikan vector lama (jika ada) di-hapus
            // supaya chat tidak retrieve data yang belum verified.
            $this->vectors->delete($productId);

            return ['success' => true, 'status' => 'skipped_not_approved', 'message' => 'Knowledge belum approved.', 'product_id' => $productId];
        }

        $content = $this->buildEmbeddingContent($product, $knowledge);
        if (trim($content) === '') {
            return ['success' => false, 'status' => 'empty_content', 'message' => 'Embedding content kosong.', 'product_id' => $productId];
        }

        $embedding = $this->gemini->embed($content);
        if (! $embedding) {
            return ['success' => false, 'status' => 'embed_failed', 'message' => 'Gemini embed gagal.', 'product_id' => $productId];
        }

        $ok = $this->vectors->upsert($productId, $content, $embedding, 'approved');
        if (! $ok) {
            return ['success' => false, 'status' => 'upsert_failed', 'message' => 'Supabase upsert gagal.', 'product_id' => $productId];
        }

        return ['success' => true, 'status' => 'synced', 'message' => 'Vector synced.', 'product_id' => $productId];
    }

    /**
     * Sync banyak produk (sequential). Return summary.
     *
     * @param  array<int, array>  $products  list product objects.
     * @param  callable|null  $onProgress  fn(string $productId, array $result, int $idx, int $total)
     * @return array{total: int, synced: int, skipped: int, failed: int, deleted: int, results: array<int, array>}
     */
    public function syncMany(array $products, ?callable $onProgress = null): array
    {
        $total = count($products);
        $synced = 0;
        $skipped = 0;
        $failed = 0;
        $deleted = 0;
        $results = [];

        foreach (array_values($products) as $idx => $product) {
            $r = $this->syncProduct($product);
            $results[] = $r;
            switch ($r['status']) {
                case 'synced':
                    $synced++;
                    break;
                case 'skipped_not_approved':
                    $skipped++;
                    break;
                case 'deleted_inactive':
                    $deleted++;
                    break;
                default:
                    $failed++;
            }
            if ($onProgress) {
                $onProgress((string) ($product['id'] ?? ''), $r, $idx + 1, $total);
            }
        }

        return [
            'total' => $total,
            'synced' => $synced,
            'skipped' => $skipped,
            'failed' => $failed,
            'deleted' => $deleted,
            'results' => $results,
        ];
    }

    /**
     * Build plain-text "embedding content" dari produk + knowledge.
     * Ini juga yang disimpan sebagai `content` di Supabase, dipakai juga untuk re-rank context.
     */
    public function buildEmbeddingContent(array $product, ?array $knowledge): string
    {
        $name = trim((string) ($product['name'] ?? ''));
        $catId = $product['category_id'] ?? '';
        $categoryName = '-';
        if ($catId) {
            $catMap = $this->firebase->getProductCategoriesMap();
            $entry = $catMap[$catId] ?? null;
            if (is_array($entry) && isset($entry['name'])) {
                $categoryName = (string) $entry['name'];
            } elseif (is_string($entry)) {
                $categoryName = $entry;
            }
        }

        $parts = [
            "Nama: {$name}.",
            "Kategori: {$categoryName}.",
        ];

        if ($knowledge) {
            if (! empty($knowledge['tipe_produk'])) {
                $parts[] = "Tipe: {$knowledge['tipe_produk']}.";
            }
            if (! empty($knowledge['brand'])) {
                $parts[] = "Brand: {$knowledge['brand']}.";
            }
            if (! empty($knowledge['manfaat'])) {
                $parts[] = "Manfaat: {$knowledge['manfaat']}.";
            }
            if (! empty($knowledge['fungsi']) && is_array($knowledge['fungsi'])) {
                $parts[] = 'Fungsi: '.implode(', ', $knowledge['fungsi']).'.';
            }
            if (! empty($knowledge['target_hewan']) && is_array($knowledge['target_hewan'])) {
                $parts[] = 'Target hewan: '.implode(', ', $knowledge['target_hewan']).'.';
            }
            if (! empty($knowledge['gejala_terkait']) && is_array($knowledge['gejala_terkait'])) {
                $parts[] = 'Gejala terkait: '.implode(', ', $knowledge['gejala_terkait']).'.';
            }
            if (! empty($knowledge['kategori_penggunaan']) && is_array($knowledge['kategori_penggunaan'])) {
                $parts[] = 'Kategori penggunaan: '.implode(', ', $knowledge['kategori_penggunaan']).'.';
            }
            if (! empty($knowledge['aturan_pakai'])) {
                $parts[] = "Aturan pakai: {$knowledge['aturan_pakai']}.";
            }
            if (! empty($knowledge['peringatan'])) {
                $parts[] = "Peringatan: {$knowledge['peringatan']}.";
            }
            if (! empty($knowledge['ukuran_varian']) && is_array($knowledge['ukuran_varian'])) {
                $parts[] = 'Ukuran/varian: '.implode(', ', $knowledge['ukuran_varian']).'.';
            }
            if (! empty($knowledge['spesifikasi']) && is_array($knowledge['spesifikasi'])) {
                $specPairs = [];
                foreach ($knowledge['spesifikasi'] as $k => $v) {
                    if ($v === null || $v === '') {
                        continue;
                    }
                    $specPairs[] = str_replace('_', ' ', $k).': '.(is_array($v) ? implode(', ', $v) : $v);
                }
                if (count($specPairs) > 0) {
                    $parts[] = 'Spesifikasi: '.implode('; ', $specPairs).'.';
                }
            }
        }

        return implode("\n", $parts);
    }
}
