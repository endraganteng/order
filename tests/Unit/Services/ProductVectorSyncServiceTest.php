<?php

namespace Tests\Unit\Services;

use App\Services\FirebaseService;
use App\Services\GeminiService;
use App\Services\ProductKnowledgeService;
use App\Services\ProductVectorSyncService;
use App\Services\SupabaseVectorService;
use PHPUnit\Framework\TestCase;

class ProductVectorSyncServiceTest extends TestCase
{
    private ProductVectorSyncService $svc;
    private FirebaseService $firebase;
    private ProductKnowledgeService $knowledge;
    private GeminiService $gemini;
    private SupabaseVectorService $vectors;

    protected function setUp(): void
    {
        parent::setUp();
        $this->firebase = $this->createMock(FirebaseService::class);
        $this->knowledge = $this->createMock(ProductKnowledgeService::class);
        $this->gemini = $this->createMock(GeminiService::class);
        $this->vectors = $this->createMock(SupabaseVectorService::class);
        $this->svc = new ProductVectorSyncService($this->firebase, $this->knowledge, $this->gemini, $this->vectors);
    }

    public function test_build_embedding_content_minimal(): void
    {
        $this->firebase->method('getProductCategoriesMap')->willReturn([
            'cat1' => ['id' => 'cat1', 'name' => 'Vitamin Unggas'],
        ]);
        $product = ['id' => 'p1', 'name' => 'Vita Chick', 'category_id' => 'cat1'];
        $content = $this->svc->buildEmbeddingContent($product, null);
        $this->assertStringContainsString('Nama: Vita Chick.', $content);
        $this->assertStringContainsString('Kategori: Vitamin Unggas.', $content);
    }

    public function test_build_embedding_content_with_full_knowledge(): void
    {
        $this->firebase->method('getProductCategoriesMap')->willReturn([
            'c1' => ['name' => 'Vitamin'],
        ]);
        $knowledge = [
            'brand' => 'Medion',
            'manfaat' => 'Meningkatkan nafsu makan ayam.',
            'fungsi' => ['vitamin', 'suplemen'],
            'target_hewan' => ['ayam', 'puyuh'],
            'gejala_terkait' => ['lemas'],
            'kategori_penggunaan' => ['vitamin'],
            'aturan_pakai' => '5g per 7L air',
            'peringatan' => 'Jauhkan dari panas',
            'ukuran_varian' => ['Sachet 5g'],
        ];
        $content = $this->svc->buildEmbeddingContent(
            ['id' => 'p1', 'name' => 'Vita Chick', 'category_id' => 'c1'],
            $knowledge
        );
        $this->assertStringContainsString('Brand: Medion.', $content);
        $this->assertStringContainsString('Manfaat: Meningkatkan nafsu makan ayam.', $content);
        $this->assertStringContainsString('Fungsi: vitamin, suplemen.', $content);
        $this->assertStringContainsString('Target hewan: ayam, puyuh.', $content);
        $this->assertStringContainsString('Aturan pakai: 5g per 7L air.', $content);
        $this->assertStringContainsString('Ukuran/varian: Sachet 5g.', $content);
    }

    public function test_sync_skips_not_approved_knowledge_and_deletes_vector(): void
    {
        $this->knowledge->method('get')->willReturn(['status' => 'pending']);
        $this->vectors->expects($this->once())->method('delete')->with('p1');
        $this->gemini->expects($this->never())->method('embed');

        $r = $this->svc->syncProduct(['id' => 'p1', 'name' => 'X', 'is_active' => true]);
        $this->assertTrue($r['success']);
        $this->assertSame('skipped_not_approved', $r['status']);
    }

    public function test_sync_deletes_vector_for_inactive_product(): void
    {
        $this->vectors->expects($this->once())->method('delete')->with('p2');
        $this->gemini->expects($this->never())->method('embed');

        $r = $this->svc->syncProduct(['id' => 'p2', 'name' => 'Y', 'is_active' => false]);
        $this->assertTrue($r['success']);
        $this->assertSame('deleted_inactive', $r['status']);
    }

    public function test_sync_full_flow_when_approved(): void
    {
        $this->firebase->method('getProductCategoriesMap')->willReturn(['c1' => ['name' => 'Vitamin']]);
        $this->knowledge->method('get')->willReturn([
            'status' => 'approved',
            'manfaat' => 'OK',
        ]);
        $this->gemini->expects($this->once())->method('embed')->willReturn(array_fill(0, 768, 0.1));
        $this->vectors->expects($this->once())->method('upsert')
            ->with('p3', $this->stringContains('Nama: Vita Chick'), $this->isType('array'), 'approved')
            ->willReturn(true);

        $r = $this->svc->syncProduct(['id' => 'p3', 'name' => 'Vita Chick', 'category_id' => 'c1', 'is_active' => true]);
        $this->assertTrue($r['success']);
        $this->assertSame('synced', $r['status']);
    }

    public function test_sync_fails_when_embed_returns_null(): void
    {
        $this->firebase->method('getProductCategoriesMap')->willReturn(['c1' => ['name' => 'Vit']]);
        $this->knowledge->method('get')->willReturn(['status' => 'approved', 'manfaat' => 'X']);
        $this->gemini->method('embed')->willReturn(null);
        $this->vectors->expects($this->never())->method('upsert');

        $r = $this->svc->syncProduct(['id' => 'p4', 'name' => 'X', 'category_id' => 'c1', 'is_active' => true]);
        $this->assertFalse($r['success']);
        $this->assertSame('embed_failed', $r['status']);
    }
}
