<?php

namespace Tests\Unit\Services;

use App\Services\GeminiService;
use App\Services\ProductKnowledgeExtractionService;
use PHPUnit\Framework\TestCase;

class ProductKnowledgeExtractionServiceTest extends TestCase
{
    private ProductKnowledgeExtractionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new ProductKnowledgeExtractionService($this->createMock(GeminiService::class));
    }

    public function test_build_prompt_includes_product_name_and_category(): void
    {
        $prompt = $this->svc->buildPrompt('Vita Chick', 'Vitamin Unggas');
        $this->assertStringContainsString('Vita Chick', $prompt);
        $this->assertStringContainsString('Vitamin Unggas', $prompt);
        $this->assertStringContainsString('SUMBER RESMI', $prompt);
        $this->assertStringContainsString('JANGAN sertakan harga', $prompt);
    }

    public function test_parse_json_handles_markdown_fence(): void
    {
        $text = "```json\n{\"brand\":\"Medion\",\"manfaat\":\"Vitamin\"}\n```";
        $r = $this->svc->parseJsonFromText($text);
        $this->assertIsArray($r);
        $this->assertSame('Medion', $r['brand']);
    }

    public function test_parse_json_handles_intro_text(): void
    {
        $text = "Berikut hasil ekstraksi:\n\n{\"brand\":\"Medion\",\"manfaat\":\"OK\"}\n\nDemikian.";
        $r = $this->svc->parseJsonFromText($text);
        $this->assertIsArray($r);
        $this->assertSame('Medion', $r['brand']);
    }

    public function test_parse_json_returns_null_on_invalid(): void
    {
        $this->assertNull($this->svc->parseJsonFromText('Tidak ada JSON di sini.'));
        $this->assertNull($this->svc->parseJsonFromText(''));
    }

    public function test_classify_sources_official_marketplace_blog(): void
    {
        $chunks = [
            ['web' => ['title' => 'Medion - Produk Vita Chick', 'uri' => 'https://medion.co.id/x']],
            ['web' => ['title' => 'Tokopedia - Vita Chick 5g', 'uri' => 'https://tokopedia.com/y']],
            ['web' => ['title' => 'Tips Ayam', 'uri' => 'https://blogspot.com/z']],
            ['web' => ['title' => 'Random', 'uri' => 'https://random.example.com']],
        ];
        $r = $this->svc->classifySources($chunks);
        $this->assertCount(4, $r);
        $this->assertSame('official_website', $r[0]['source_type']);
        $this->assertSame('marketplace', $r[1]['source_type']);
        $this->assertSame('blog', $r[2]['source_type']);
        $this->assertSame('unknown', $r[3]['source_type']);
    }

    public function test_classify_sources_dedupes(): void
    {
        $chunks = [
            ['web' => ['title' => 'Medion', 'uri' => 'https://medion.co.id/a']],
            ['web' => ['title' => 'Medion', 'uri' => 'https://medion.co.id/a']],
        ];
        $r = $this->svc->classifySources($chunks);
        $this->assertCount(1, $r);
    }

    public function test_classify_sources_skips_chunks_without_web_dict(): void
    {
        $chunks = [
            ['unknown_field' => 'foo'],
            ['web' => ['title' => 'Sanbe', 'uri' => 'https://sanbe.co.id']],
        ];
        $r = $this->svc->classifySources($chunks);
        $this->assertCount(1, $r);
        $this->assertSame('Sanbe', $r[0]['title']);
    }

    public function test_classify_product_type_medical(): void
    {
        $this->assertSame('medical', $this->svc->classifyProductType('OBAT-OBATAN AYAM'));
        $this->assertSame('medical', $this->svc->classifyProductType('OBAT-OBATAN KUCING'));
        $this->assertSame('medical', $this->svc->classifyProductType('NECTAR BURUNG'));
        $this->assertSame('medical', $this->svc->classifyProductType('PERAWATAN KUCING'));
        $this->assertSame('medical', $this->svc->classifyProductType('OBAT IKAN'));
    }

    public function test_classify_product_type_food(): void
    {
        $this->assertSame('food', $this->svc->classifyProductType('PAKAN AYAM'));
        $this->assertSame('food', $this->svc->classifyProductType('PAKAN IKAN'));
        $this->assertSame('food', $this->svc->classifyProductType('PAKAN KUCING'));
        $this->assertSame('food', $this->svc->classifyProductType('UMPAN'));
        $this->assertSame('food', $this->svc->classifyProductType('ESSENCE'));
    }

    public function test_classify_product_type_equipment(): void
    {
        $this->assertSame('equipment', $this->svc->classifyProductType('JORAN PANCING'));
        $this->assertSame('equipment', $this->svc->classifyProductType('REEL PANCING'));
        $this->assertSame('equipment', $this->svc->classifyProductType('KAIL PANCING'));
        $this->assertSame('equipment', $this->svc->classifyProductType('SENAR PANCING'));
        $this->assertSame('equipment', $this->svc->classifyProductType('AKSESORIS KUCING'));
        $this->assertSame('equipment', $this->svc->classifyProductType('AKSESORIS PANCING'));
        $this->assertSame('equipment', $this->svc->classifyProductType('KANDANG'));
        $this->assertSame('equipment', $this->svc->classifyProductType('PASIR KUCING'));
        $this->assertSame('equipment', $this->svc->classifyProductType('SPAREPART REEL'));
        $this->assertSame('equipment', $this->svc->classifyProductType('TEGEK PANCING'));
    }

    public function test_classify_product_type_livestock(): void
    {
        $this->assertSame('livestock', $this->svc->classifyProductType('HEWAN'));
    }

    public function test_classify_product_type_pest_control(): void
    {
        $this->assertSame('pest_control', $this->svc->classifyProductType('RACUN TIKUS'));
        $this->assertSame('pest_control', $this->svc->classifyProductType('PERANGKAP HEWAN'));
    }

    public function test_classify_product_type_general_fallback(): void
    {
        $this->assertSame('general', $this->svc->classifyProductType(null));
        $this->assertSame('general', $this->svc->classifyProductType(''));
        $this->assertSame('general', $this->svc->classifyProductType('UNKNOWN CATEGORY XYZ'));
    }

    public function test_build_prompt_uses_correct_schema_per_type(): void
    {
        $medical = $this->svc->buildPrompt('Vita Chick', 'OBAT-OBATAN AYAM');
        $this->assertStringContainsString('"tipe_produk": "medical"', $medical);
        $this->assertStringContainsString('JANGAN klaim "menyembuhkan"', $medical);

        $food = $this->svc->buildPrompt('Pakan Ayam Starter', 'PAKAN AYAM');
        $this->assertStringContainsString('"tipe_produk": "food"', $food);
        $this->assertStringContainsString('PAKAN: cari tahu fase', $food);

        $eq = $this->svc->buildPrompt('Joran Antena 180cm', 'JORAN PANCING');
        $this->assertStringContainsString('"tipe_produk": "equipment"', $eq);
        $this->assertStringContainsString('ALAT PANCING', $eq);
        $this->assertStringContainsString('"line_weight"', $eq);

        $live = $this->svc->buildPrompt('Ayam Bangkok', 'HEWAN');
        $this->assertStringContainsString('"tipe_produk": "livestock"', $live);
        $this->assertStringContainsString('HEWAN HIDUP', $live);

        $pest = $this->svc->buildPrompt('Racun Tikus Mao Uung', 'RACUN TIKUS');
        $this->assertStringContainsString('"tipe_produk": "pest_control"', $pest);
        $this->assertStringContainsString('peringatan keamanan', $pest);
    }
}
