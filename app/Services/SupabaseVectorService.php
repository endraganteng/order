<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SupabaseVectorService
 *
 * HTTP client untuk Supabase PostgREST API.
 * Khusus untuk operasi vector store di tabel ai_product_vectors.
 *
 * Operasi:
 *   - upsert(productId, content, embedding, status) : insert/update vektor
 *   - match(queryEmbedding, matchCount)            : RPC match_products (cosine similarity)
 *   - delete(productId)                             : hapus vektor produk
 *   - getStats()                                    : count rows by status
 */
class SupabaseVectorService
{
    protected string $url;

    protected string $serviceKey;

    protected string $table;

    protected int $timeoutSeconds;

    public function __construct()
    {
        $this->url = rtrim((string) config('ai_product_assistant.supabase_url', ''), '/');
        $this->serviceKey = (string) config('ai_product_assistant.supabase_service_role_key', '');
        $this->table = (string) config('ai_product_assistant.supabase_vector_table', 'ai_product_vectors');
        $this->timeoutSeconds = (int) config('ai_product_assistant.supabase_timeout_seconds', 30);
    }

    public function isConfigured(): bool
    {
        return $this->url !== '' && $this->serviceKey !== '';
    }

    protected function headers(array $extra = []): array
    {
        return array_merge([
            'apikey' => $this->serviceKey,
            'Authorization' => 'Bearer '.$this->serviceKey,
            'Content-Type' => 'application/json',
        ], $extra);
    }

    /**
     * Upsert single vector row.
     *
     * @param  string  $productId  Firebase product key.
     * @param  string  $content  Plain text content yang di-embed.
     * @param  array<int, float>  $embedding  Vector floats (768 dim).
     * @param  string  $status  approved|pending|deprecated
     */
    public function upsert(string $productId, string $content, array $embedding, string $status = 'approved'): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $endpoint = "{$this->url}/rest/v1/{$this->table}?on_conflict=product_id";

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->headers([
                    'Prefer' => 'resolution=merge-duplicates,return=minimal',
                ]))
                ->post($endpoint, [[
                    'product_id' => $productId,
                    'content' => $content,
                    'embedding' => $embedding,
                    'status' => $status,
                    'updated_at' => now()->toIso8601String(),
                ]]);

            if (! $response->successful()) {
                Log::warning('Supabase upsert failed', [
                    'product_id' => $productId,
                    'status' => $response->status(),
                    'body' => $this->truncate($response->body()),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Supabase upsert exception: '.$e->getMessage(), ['product_id' => $productId]);

            return false;
        }
    }

    /**
     * Match products via RPC match_products (cosine similarity).
     *
     * @param  array<int, float>  $queryEmbedding
     * @return array<int, array{product_id: string, content: string, similarity: float}>
     */
    public function match(array $queryEmbedding, int $matchCount = 10): array
    {
        if (! $this->isConfigured() || count($queryEmbedding) === 0) {
            return [];
        }

        $endpoint = "{$this->url}/rest/v1/rpc/match_products";

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->headers())
                ->post($endpoint, [
                    'query_embedding' => $queryEmbedding,
                    'match_count' => $matchCount,
                ]);

            if (! $response->successful()) {
                Log::warning('Supabase match failed', [
                    'status' => $response->status(),
                    'body' => $this->truncate($response->body()),
                ]);

                return [];
            }

            $rows = $response->json();
            if (! is_array($rows)) {
                return [];
            }

            return array_map(function ($row) {
                return [
                    'product_id' => (string) ($row['product_id'] ?? ''),
                    'content' => (string) ($row['content'] ?? ''),
                    'similarity' => (float) ($row['similarity'] ?? 0),
                ];
            }, $rows);
        } catch (\Throwable $e) {
            Log::error('Supabase match exception: '.$e->getMessage());

            return [];
        }
    }

    public function delete(string $productId): bool
    {
        if (! $this->isConfigured() || $productId === '') {
            return false;
        }

        $endpoint = "{$this->url}/rest/v1/{$this->table}?product_id=eq.".urlencode($productId);

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->headers([
                    'Prefer' => 'return=minimal',
                ]))
                ->delete($endpoint);

            if (! $response->successful()) {
                Log::warning('Supabase delete failed', [
                    'product_id' => $productId,
                    'status' => $response->status(),
                    'body' => $this->truncate($response->body()),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Supabase delete exception: '.$e->getMessage(), ['product_id' => $productId]);

            return false;
        }
    }

    /**
     * Get total approved vectors count via HEAD request.
     */
    public function countApproved(): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $endpoint = "{$this->url}/rest/v1/{$this->table}?status=eq.approved&select=product_id";

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->headers([
                    'Prefer' => 'count=exact',
                    'Range-Unit' => 'items',
                    'Range' => '0-0',
                ]))
                ->get($endpoint);

            $contentRange = $response->header('Content-Range');
            if (! $contentRange || ! str_contains($contentRange, '/')) {
                return 0;
            }
            $parts = explode('/', $contentRange);

            return (int) end($parts);
        } catch (\Throwable $e) {
            Log::error('Supabase countApproved exception: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Check apakah product_id sudah ada di vector store.
     */
    public function exists(string $productId): bool
    {
        if (! $this->isConfigured() || $productId === '') {
            return false;
        }

        $endpoint = "{$this->url}/rest/v1/{$this->table}?product_id=eq.".urlencode($productId).'&select=product_id&limit=1';

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders($this->headers())
                ->get($endpoint);

            if (! $response->successful()) {
                return false;
            }
            $rows = $response->json();

            return is_array($rows) && count($rows) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function truncate(string $s, int $max = 800): string
    {
        return strlen($s) > $max ? substr($s, 0, $max).'...' : $s;
    }
}
