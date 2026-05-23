<?php

namespace Tests\Unit\Services;

use App\Services\VariantDetectorService;
use PHPUnit\Framework\TestCase;

class VariantDetectorServiceTest extends TestCase
{
    private VariantDetectorService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new VariantDetectorService;
    }

    public function test_detect_simple_no_variant(): void
    {
        $r = $this->svc->detect('Pelet Apung Hi-Pro');
        $this->assertSame('Pelet Apung Hi-Pro', $r['base']);
        $this->assertNull($r['variant']);
        $this->assertFalse($r['has_variant']);
    }

    public function test_detect_with_variant(): void
    {
        $r = $this->svc->detect('Vita Chick - Sachet 5g');
        $this->assertSame('Vita Chick', $r['base']);
        $this->assertSame('Sachet 5g', $r['variant']);
        $this->assertTrue($r['has_variant']);
    }

    public function test_detect_dash_without_spaces_is_not_variant(): void
    {
        $r = $this->svc->detect('Pelet-Apung-Hi-Pro');
        $this->assertSame('Pelet-Apung-Hi-Pro', $r['base']);
        $this->assertNull($r['variant']);
        $this->assertFalse($r['has_variant']);
    }

    public function test_detect_handles_multiple_dashes(): void
    {
        // split max 2 — semua sisa setelah delimiter pertama jadi variant.
        $r = $this->svc->detect('Pakan Ayam - 10 kg - A');
        $this->assertSame('Pakan Ayam', $r['base']);
        $this->assertSame('10 kg - A', $r['variant']);
    }

    public function test_detect_trims_whitespace(): void
    {
        $r = $this->svc->detect('   Vita Chick   -   Sachet 5g   ');
        $this->assertSame('Vita Chick', $r['base']);
        $this->assertSame('Sachet 5g', $r['variant']);
    }

    public function test_detect_empty_input(): void
    {
        $r = $this->svc->detect('');
        $this->assertSame('', $r['base']);
        $this->assertNull($r['variant']);
        $this->assertFalse($r['has_variant']);
    }

    public function test_group_by_base_picks_lowest_id_as_primary(): void
    {
        $products = [
            ['id' => 'b002', 'name' => 'Vita Chick - Sachet 10g'],
            ['id' => 'a001', 'name' => 'Vita Chick - Sachet 5g'],
            ['id' => 'c003', 'name' => 'Pelet Apung Hi-Pro'],
            ['id' => 'd004', 'name' => 'Vita Chick'],
        ];
        $groups = $this->svc->groupByBase($products);

        $this->assertArrayHasKey('Vita Chick', $groups);
        $this->assertSame('a001', $groups['Vita Chick']['primary_id']);
        $this->assertCount(3, $groups['Vita Chick']['members']);

        $this->assertArrayHasKey('Pelet Apung Hi-Pro', $groups);
        $this->assertSame('c003', $groups['Pelet Apung Hi-Pro']['primary_id']);
    }

    public function test_group_skips_products_without_id_or_name(): void
    {
        $products = [
            ['id' => '', 'name' => 'Vita Chick'],
            ['id' => 'a001', 'name' => ''],
            ['id' => 'b002', 'name' => 'Vita Chick - Sachet 5g'],
        ];
        $groups = $this->svc->groupByBase($products);
        $this->assertCount(1, $groups);
        $this->assertSame('b002', $groups['Vita Chick']['primary_id']);
    }

    public function test_is_primary_true_for_standalone(): void
    {
        $products = [
            ['id' => 'x001', 'name' => 'Pakan Anjing'],
        ];
        $this->assertTrue($this->svc->isPrimary('x001', 'Pakan Anjing', $products));
    }

    public function test_is_primary_false_for_non_lowest_variant(): void
    {
        $products = [
            ['id' => 'a001', 'name' => 'Vita Chick - Sachet 5g'],
            ['id' => 'b002', 'name' => 'Vita Chick - Sachet 10g'],
        ];
        $this->assertTrue($this->svc->isPrimary('a001', 'Vita Chick - Sachet 5g', $products));
        $this->assertFalse($this->svc->isPrimary('b002', 'Vita Chick - Sachet 10g', $products));
    }
}
