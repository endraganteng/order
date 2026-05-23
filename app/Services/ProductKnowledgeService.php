<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Database;

/**
 * ProductKnowledgeService
 *
 * Bridge ke Firebase RTDB path `product_knowledge_base/{product_id}`.
 * Knowledge yang disimpan adalah hasil ekstraksi grounded search (oleh ProductKnowledgeExtractionService)
 * yang sudah di-approve admin (atau auto-inherit dari base produk).
 *
 * Struktur node knowledge (per produk):
 * {
 *   "brand": "Medion",
 *   "manfaat": "...",
 *   "fungsi": ["meningkatkan nafsu makan", ...],
 *   "target_hewan": ["ayam", "puyuh"],
 *   "gejala_terkait": ["lemas", "tidak nafsu makan"],
 *   "kategori_penggunaan": ["vitamin", "suplemen"],
 *   "aturan_pakai": "5 gram per 7 liter air ...",
 *   "peringatan": "Simpan di tempat sejuk dan kering",
 *   "ukuran_varian": ["Sachet 5g"],
 *   "sources": [
 *      {"title":"Medion","url":"https://medion.co.id/...","source_type":"official_website"},
 *      ...
 *   ],
 *   "confidence_score": 0.9,
 *   "status": "approved",     // approved | pending | needs_review | rejected
 *   "review_note": "...",
 *   "is_inherited": false,
 *   "inherited_from": null,
 *   "approved_by": "uid_or_email",
 *   "approved_at": 1234567890,
 *   "updated_at": 1234567890
 * }
 */
class ProductKnowledgeService
{
    protected Database $database;

    protected string $basePath;

    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->basePath = (string) config('ai_product_assistant.firebase_knowledge_path', 'product_knowledge_base');
    }

    /**
     * Ambil knowledge satu produk.
     */
    public function get(string $productId): ?array
    {
        if ($productId === '') {
            return null;
        }

        try {
            $snapshot = $this->database->getReference("{$this->basePath}/{$productId}")->getSnapshot();
            if (! $snapshot->exists()) {
                return null;
            }
            $value = $snapshot->getValue();

            return is_array($value) ? $value : null;
        } catch (\Throwable $e) {
            Log::error('ProductKnowledge get exception: '.$e->getMessage(), ['product_id' => $productId]);

            return null;
        }
    }

    /**
     * Bulk get knowledge by product IDs.
     *
     * @param  array<int, string>  $productIds
     * @return array<string, array>  product_id => knowledge
     */
    public function getMany(array $productIds): array
    {
        $result = [];
        foreach ($productIds as $id) {
            $k = $this->get((string) $id);
            if ($k !== null) {
                $result[(string) $id] = $k;
            }
        }

        return $result;
    }

    /**
     * Save (overwrite) knowledge node.
     */
    public function save(string $productId, array $knowledge): bool
    {
        if ($productId === '') {
            return false;
        }

        try {
            $payload = $knowledge;
            $payload['updated_at'] = time();
            $this->database->getReference("{$this->basePath}/{$productId}")->set($payload);

            return true;
        } catch (\Throwable $e) {
            Log::error('ProductKnowledge save exception: '.$e->getMessage(), ['product_id' => $productId]);

            return false;
        }
    }

    /**
     * Update partial fields.
     */
    public function update(string $productId, array $partial): bool
    {
        if ($productId === '' || count($partial) === 0) {
            return false;
        }

        try {
            $partial['updated_at'] = time();
            $this->database->getReference("{$this->basePath}/{$productId}")->update($partial);

            return true;
        } catch (\Throwable $e) {
            Log::error('ProductKnowledge update exception: '.$e->getMessage(), ['product_id' => $productId]);

            return false;
        }
    }

    public function delete(string $productId): bool
    {
        if ($productId === '') {
            return false;
        }

        try {
            $this->database->getReference("{$this->basePath}/{$productId}")->remove();

            return true;
        } catch (\Throwable $e) {
            Log::error('ProductKnowledge delete exception: '.$e->getMessage(), ['product_id' => $productId]);

            return false;
        }
    }

    public function approve(string $productId, string $approverIdentifier): bool
    {
        return $this->update($productId, [
            'status' => 'approved',
            'approved_by' => $approverIdentifier,
            'approved_at' => time(),
        ]);
    }

    public function reject(string $productId, string $approverIdentifier, ?string $reason = null): bool
    {
        return $this->update($productId, [
            'status' => 'rejected',
            'approved_by' => $approverIdentifier,
            'approved_at' => time(),
            'review_note' => $reason,
        ]);
    }

    /**
     * Cek apakah knowledge sudah ada (any status).
     */
    public function exists(string $productId): bool
    {
        return $this->get($productId) !== null;
    }
}
