<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeminiService
 *
 * HTTP client untuk Google Generative Language API (Gemini).
 * Mendukung 3 operasi utama:
 *   - embed()           : generate embedding vector (default 768 dim)
 *   - chat()            : plain chat completion (no tools)
 *   - groundedExtract() : chat completion + Google Search grounding (untuk web research)
 *
 * Konfigurasi diambil dari config('ai_product_assistant.*').
 */
class GeminiService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected string $embeddingModel;

    protected string $chatModel;

    protected int $embeddingDimension;

    protected int $timeoutSeconds;

    public function __construct()
    {
        $this->apiKey = (string) config('ai_product_assistant.gemini_api_key', '');
        $this->baseUrl = rtrim((string) config('ai_product_assistant.gemini_base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->embeddingModel = (string) config('ai_product_assistant.gemini_embedding_model', 'gemini-embedding-001');
        $this->chatModel = (string) config('ai_product_assistant.gemini_chat_model', 'gemini-2.5-flash');
        $this->embeddingDimension = (int) config('ai_product_assistant.gemini_embedding_dimension', 768);
        $this->timeoutSeconds = (int) config('ai_product_assistant.gemini_timeout_seconds', 60);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Generate single-text embedding.
     *
     * @return array<int, float>|null Vector floats, atau null jika gagal.
     */
    public function embed(string $text): ?array
    {
        if (! $this->isConfigured() || trim($text) === '') {
            return null;
        }

        $url = "{$this->baseUrl}/models/{$this->embeddingModel}:embedContent?key={$this->apiKey}";

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'content' => [
                        'parts' => [['text' => $text]],
                    ],
                    'outputDimensionality' => $this->embeddingDimension,
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini embed failed', [
                    'status' => $response->status(),
                    'body' => $this->truncate($response->body()),
                ]);

                return null;
            }

            $values = $response->json('embedding.values');
            if (! is_array($values) || count($values) === 0) {
                Log::warning('Gemini embed: empty values', ['body' => $this->truncate($response->body())]);

                return null;
            }

            return array_map('floatval', $values);
        } catch (\Throwable $e) {
            Log::error('Gemini embed exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Plain chat completion (no tools, no grounding).
     *
     * @param  string  $prompt  User prompt (sistem + konteks digabung).
     * @param  float  $temperature  0.0 - 1.0
     * @return string|null Text response, atau null jika gagal.
     */
    public function chat(string $prompt, float $temperature = 0.4): ?string
    {
        if (! $this->isConfigured() || trim($prompt) === '') {
            return null;
        }

        $url = "{$this->baseUrl}/models/{$this->chatModel}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'contents' => [[
                        'parts' => [['text' => $prompt]],
                    ]],
                    'generationConfig' => [
                        'temperature' => $temperature,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini chat failed', [
                    'status' => $response->status(),
                    'body' => $this->truncate($response->body()),
                ]);

                return null;
            }

            return $this->extractText($response->json());
        } catch (\Throwable $e) {
            Log::error('Gemini chat exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Grounded extraction: chat dengan Google Search tool.
     *
     * @return array{text: string|null, grounding_chunks: array, web_search_queries: array, raw: array}|null
     */
    public function groundedExtract(string $prompt, float $temperature = 0.2): ?array
    {
        if (! $this->isConfigured() || trim($prompt) === '') {
            return null;
        }

        $url = "{$this->baseUrl}/models/{$this->chatModel}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post($url, [
                    'contents' => [[
                        'parts' => [['text' => $prompt]],
                    ]],
                    'tools' => [['google_search' => (object) []]],
                    'generationConfig' => [
                        'temperature' => $temperature,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini grounded failed', [
                    'status' => $response->status(),
                    'body' => $this->truncate($response->body()),
                ]);

                return null;
            }

            $body = $response->json();
            $candidate = $body['candidates'][0] ?? null;

            return [
                'text' => $this->extractText($body),
                'grounding_chunks' => $candidate['groundingMetadata']['groundingChunks'] ?? [],
                'web_search_queries' => $candidate['groundingMetadata']['webSearchQueries'] ?? [],
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Gemini grounded exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Ekstrak text dari response Gemini, handle multi-part.
     */
    protected function extractText(?array $body): ?string
    {
        if (! $body) {
            return null;
        }
        $parts = $body['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= $part['text'];
            }
        }
        $text = trim($text);

        return $text === '' ? null : $text;
    }

    protected function truncate(string $s, int $max = 800): string
    {
        return strlen($s) > $max ? substr($s, 0, $max).'...' : $s;
    }
}
