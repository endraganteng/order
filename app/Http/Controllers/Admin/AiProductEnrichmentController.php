<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProductEnrichmentBatch;
use App\Models\AiProductEnrichmentJob;
use App\Models\AiProductEnrichmentLog;
use App\Services\BatchProcessService;
use App\Services\FirebaseService;
use App\Services\ProductEnrichmentService;
use App\Services\ProductKnowledgeService;
use App\Services\ProductVectorSyncService;
use App\Services\VariantDetectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AiProductEnrichmentController extends Controller
{
    public function __construct(
        protected FirebaseService $firebase,
        protected ProductEnrichmentService $enrichment,
        protected ProductKnowledgeService $knowledge,
        protected ProductVectorSyncService $vectorSync,
        protected VariantDetectorService $variant,
        protected BatchProcessService $batchProcess,
    ) {
    }

    /**
     * Index page: show enrichment jobs + filter + stats.
     */
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $search = trim((string) $request->string('q')->toString());
        $perPage = (int) max(10, min(100, (int) $request->input('per_page', 25)));

        $query = AiProductEnrichmentJob::query()->orderByDesc('id');
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', '%'.$search.'%')
                  ->orWhere('product_id', 'like', '%'.$search.'%')
                  ->orWhere('base_name', 'like', '%'.$search.'%');
            });
        }
        $jobs = $query->paginate($perPage)->appends($request->query());

        $stats = [
            'total' => AiProductEnrichmentJob::count(),
            'pending_review' => AiProductEnrichmentJob::where('status', 'pending_review')->count(),
            'needs_review' => AiProductEnrichmentJob::where('status', 'needs_review')->count(),
            'approved' => AiProductEnrichmentJob::where('status', 'approved')->count(),
            'rejected' => AiProductEnrichmentJob::where('status', 'rejected')->count(),
            'failed' => AiProductEnrichmentJob::where('status', 'failed')->count(),
        ];

        return view('admin.ai_products.index', compact('jobs', 'stats', 'status', 'search', 'perPage'));
    }

    /**
     * Search Firebase products yang BELUM punya knowledge / pending — picker untuk Generate.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = mb_strtolower(trim((string) $request->string('q')->toString()));
        $onlyMissing = $request->boolean('only_missing', false);
        $limit = (int) max(5, min(50, (int) $request->input('limit', 20)));

        $products = $this->firebase->getActiveProducts();
        $rows = [];
        foreach ($products as $p) {
            $name = (string) ($p['name'] ?? '');
            if ($q !== '' && ! str_contains(mb_strtolower($name), $q)) {
                continue;
            }
            $id = (string) ($p['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $hasKnowledge = $this->knowledge->exists($id);
            if ($onlyMissing && $hasKnowledge) {
                continue;
            }
            $detected = $this->variant->detect($name);
            $rows[] = [
                'id' => $id,
                'name' => $name,
                'base' => $detected['base'],
                'variant_label' => $detected['variant'],
                'has_knowledge' => $hasKnowledge,
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return response()->json(['success' => true, 'products' => $rows]);
    }

    /**
     * POST: generate enrichment 1 produk.
     */
    public function generate(Request $request): JsonResponse
    {
        $productId = trim((string) $request->input('product_id', ''));
        if ($productId === '') {
            return response()->json(['success' => false, 'message' => 'product_id wajib.'], 422);
        }

        $admin = $this->adminIdentifier();
        $result = $this->enrichment->enrichByProductId($productId, $admin);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * POST: batch generate 1..N produk yang belum punya knowledge.
     */
    public function generateBatch(Request $request): JsonResponse
    {
        $batchLimit = (int) min(
            (int) config('ai_product_assistant.enrichment_batch_limit', 20),
            max(1, (int) $request->input('limit', 20))
        );

        $admin = $this->adminIdentifier();
        $allProducts = $this->firebase->getActiveProducts();

        $candidates = [];
        foreach ($allProducts as $p) {
            $id = (string) ($p['id'] ?? '');
            if ($id === '' || $this->knowledge->exists($id)) {
                continue;
            }
            $candidates[] = $p;
            if (count($candidates) >= $batchLimit) {
                break;
            }
        }

        $results = [];
        foreach ($candidates as $product) {
            try {
                $r = $this->enrichment->enrichProduct($product, $admin, $allProducts);
            } catch (\Throwable $e) {
                Log::error('Batch enrich exception: '.$e->getMessage(), ['product' => $product['id'] ?? '']);
                $r = ['success' => false, 'status' => 'failed', 'message' => $e->getMessage()];
            }
            $results[] = [
                'product_id' => $product['id'] ?? '',
                'product_name' => $product['name'] ?? '',
                'success' => $r['success'] ?? false,
                'status' => $r['status'] ?? 'failed',
                'message' => $r['message'] ?? '',
            ];
        }

        return response()->json([
            'success' => true,
            'requested' => count($candidates),
            'results' => $results,
        ]);
    }

    /**
     * Show: detail satu job + knowledge.
     */
    public function show(int $id)
    {
        $job = AiProductEnrichmentJob::findOrFail($id);
        $product = $this->firebase->getProductById($job->product_id);
        $knowledge = $this->knowledge->get($job->product_id);
        $logs = AiProductEnrichmentLog::where('job_id', $id)->orderBy('id')->get();

        $categoryName = '-';
        if ($product && ! empty($product['category_id'])) {
            $map = $this->firebase->getProductCategoriesMap();
            $entry = $map[$product['category_id']] ?? null;
            $categoryName = is_array($entry) ? (string) ($entry['name'] ?? '-') : (string) ($entry ?? '-');
        }

        return view('admin.ai_products.show', compact('job', 'product', 'knowledge', 'logs', 'categoryName'));
    }

    /**
     * POST: approve job → trigger vector sync.
     */
    public function approve(int $id): JsonResponse
    {
        $admin = $this->adminIdentifier();
        $result = $this->enrichment->approveJob($id, $admin);
        if (! $result['success']) {
            return response()->json($result, 400);
        }
        // Vector sync setelah approve.
        $sync = $this->vectorSync->syncProductId($result['product_id']);
        $result['vector_sync'] = $sync;

        return response()->json($result);
    }

    /**
     * POST: reject job.
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $reason = trim((string) $request->input('reason', ''));
        $admin = $this->adminIdentifier();
        $result = $this->enrichment->rejectJob($id, $admin, $reason !== '' ? $reason : null);
        // Hapus vector kalau ada (defensif).
        if ($result['success'] && ! empty($result['product_id'])) {
            app(\App\Services\SupabaseVectorService::class)->delete($result['product_id']);
        }

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * PUT: edit knowledge fields manually (admin override).
     */
    public function updateKnowledge(Request $request, int $id): JsonResponse
    {
        $job = AiProductEnrichmentJob::findOrFail($id);

        $payload = $request->validate([
            'brand' => 'nullable|string|max:255',
            'manfaat' => 'nullable|string|max:5000',
            'fungsi' => 'nullable|array',
            'target_hewan' => 'nullable|array',
            'gejala_terkait' => 'nullable|array',
            'kategori_penggunaan' => 'nullable|array',
            'aturan_pakai' => 'nullable|string|max:5000',
            'peringatan' => 'nullable|string|max:5000',
            'ukuran_varian' => 'nullable|array',
            'spesifikasi' => 'nullable|array',
        ]);
        $clean = [];
        foreach ($payload as $k => $v) {
            if ($k === 'spesifikasi' && is_array($v)) {
                // Spesifikasi: key-value, hapus pasangan kosong + normalisasi key.
                $spec = [];
                foreach ($v as $sk => $sv) {
                    $sk = trim((string) $sk);
                    $sv = is_array($sv) ? implode(', ', $sv) : trim((string) $sv);
                    if ($sk === '' || $sv === '') {
                        continue;
                    }
                    $cleanKey = preg_replace('/\s+/', '_', mb_strtolower($sk));
                    $cleanKey = preg_replace('/[^a-z0-9_]/u', '', $cleanKey);
                    if ($cleanKey === '') {
                        continue;
                    }
                    $spec[$cleanKey] = $sv;
                }
                $clean[$k] = $spec;
            } elseif (is_array($v)) {
                $clean[$k] = array_values(array_filter(array_map(fn ($x) => trim((string) $x), $v), fn ($x) => $x !== ''));
            } else {
                $clean[$k] = $v === null ? null : trim((string) $v);
            }
        }

        $ok = $this->knowledge->update($job->product_id, $clean);
        if (! $ok) {
            return response()->json(['success' => false, 'message' => 'Gagal update knowledge.'], 500);
        }

        $this->enrichment->log($job->id, $job->product_id, 'manual_edit', 'Knowledge di-edit manual oleh admin.', $clean, null);

        return response()->json(['success' => true, 'message' => 'Knowledge updated.']);
    }

    /**
     * POST: re-sync vector untuk produk yang sudah approved.
     */
    public function resyncVector(int $id): JsonResponse
    {
        $job = AiProductEnrichmentJob::findOrFail($id);
        if ($job->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Job belum approved.'], 400);
        }
        $r = $this->vectorSync->syncProductId($job->product_id);

        return response()->json(['success' => $r['success'], 'message' => $r['message'], 'sync' => $r]);
    }

    /**
     * POST: spawn batch background process untuk enrichment massal.
     * Optional flags: auto_approve, auto_sync, only_missing, category_id, limit.
     */
    public function batchStart(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => 'required|in:enrichment,vector_sync',
            'limit' => 'nullable|integer|min:1|max:500',
            'auto_approve' => 'nullable|boolean',
            'auto_sync' => 'nullable|boolean',
            'only_missing' => 'nullable|boolean',
            'include_all' => 'nullable|boolean',
            'category_id' => 'nullable|string|max:64',
        ]);

        // Cegah dua batch jalan barengan untuk mode yang sama.
        $existing = AiProductEnrichmentBatch::whereIn('status', ['queued', 'running'])
            ->where('mode', $data['mode'])
            ->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Batch {$data['mode']} #{$existing->id} masih berjalan. Tunggu selesai atau cancel dulu.",
                'batch_id' => $existing->id,
            ], 409);
        }

        $batch = $this->batchProcess->startBatch([
            'mode' => $data['mode'],
            'auto_approve' => (bool) ($data['auto_approve'] ?? false),
            'auto_sync' => (bool) ($data['auto_sync'] ?? false),
            'options' => [
                'limit' => (int) ($data['limit'] ?? 20),
                'only_missing' => (bool) ($data['only_missing'] ?? true),
                'include_all' => (bool) ($data['include_all'] ?? false),
                'category_id' => $data['category_id'] ?? null,
            ],
            'initiated_by' => $this->adminIdentifier(),
        ]);

        return response()->json([
            'success' => true,
            'batch_id' => $batch->id,
            'status' => $batch->status,
            'message' => $batch->status === 'failed' ? $batch->last_message : 'Batch dimulai di latar belakang.',
        ]);
    }

    /**
     * GET: status batch (untuk polling UI).
     */
    public function batchStatus(int $id): JsonResponse
    {
        $batch = AiProductEnrichmentBatch::find($id);
        if (! $batch) {
            return response()->json(['success' => false, 'message' => 'Batch tidak ditemukan.'], 404);
        }
        $stale = $batch->isStale(120);
        if ($stale && in_array($batch->status, ['queued', 'running'], true)) {
            // Stuck >2 menit tanpa heartbeat → tandai failed
            $batch->update([
                'status' => 'failed',
                'finished_at' => now(),
                'last_message' => 'Batch dianggap mati (heartbeat berhenti). Mungkin server crash.',
            ]);
        }

        return response()->json([
            'success' => true,
            'batch' => [
                'id' => $batch->id,
                'mode' => $batch->mode,
                'status' => $batch->status,
                'total_items' => $batch->total_items,
                'processed_items' => $batch->processed_items,
                'success_count' => $batch->success_count,
                'failed_count' => $batch->failed_count,
                'skipped_count' => $batch->skipped_count,
                'progress_pct' => $batch->progressPct(),
                'auto_approve' => $batch->auto_approve,
                'auto_sync' => $batch->auto_sync,
                'current_product_id' => $batch->current_product_id,
                'current_product_name' => $batch->current_product_name,
                'last_message' => $batch->last_message,
                'started_at' => $batch->started_at?->format('Y-m-d H:i:s'),
                'finished_at' => $batch->finished_at?->format('Y-m-d H:i:s'),
                'heartbeat_at' => $batch->heartbeat_at?->format('Y-m-d H:i:s'),
                'is_stale' => $stale,
                'summary' => $batch->summary,
                'is_terminal' => in_array($batch->status, ['completed', 'failed', 'cancelled'], true),
            ],
        ]);
    }

    /**
     * POST: cancel batch.
     */
    public function batchCancel(int $id): JsonResponse
    {
        $ok = $this->batchProcess->cancelBatch($id);

        return response()->json([
            'success' => $ok,
            'message' => $ok ? 'Batch akan berhenti pada item berikutnya.' : 'Batch tidak bisa di-cancel (sudah selesai atau tidak ditemukan).',
        ], $ok ? 200 : 400);
    }

    /**
     * GET: list batch terbaru (untuk panel history).
     */
    public function batchList(): JsonResponse
    {
        $batches = AiProductEnrichmentBatch::orderByDesc('id')->limit(10)->get()->map(fn ($b) => [
            'id' => $b->id,
            'mode' => $b->mode,
            'status' => $b->status,
            'total_items' => $b->total_items,
            'processed_items' => $b->processed_items,
            'success_count' => $b->success_count,
            'failed_count' => $b->failed_count,
            'progress_pct' => $b->progressPct(),
            'started_at' => $b->started_at?->format('Y-m-d H:i:s'),
            'finished_at' => $b->finished_at?->format('Y-m-d H:i:s'),
        ]);

        return response()->json(['success' => true, 'batches' => $batches]);
    }

    protected function adminIdentifier(): string
    {
        return (string) (session('admin_email') ?? session('admin_id') ?? 'admin');
    }
}
