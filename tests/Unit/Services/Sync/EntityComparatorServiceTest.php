<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sync;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\Sync\EntityComparatorService;

class EntityComparatorServiceTest extends TestCase
{
    private EntityComparatorService $comparator;

    protected function setUp(): void
    {
        $this->comparator = new EntityComparatorService();
    }

    public function test_get_catalog_extractor_script_uses_chunked_pagination_and_runtime_cache_flush(): void
    {
        $script = $this->comparator->getCatalogExtractorScript();

        // Verificar que NO usa límite unbounded -1
        $this->assertStringNotContainsString("'limit' => -1", $script);
        $this->assertStringNotContainsString('"limit" => -1', $script);

        // Verificar chunking y paginación
        $this->assertStringContainsString('$chunkSize = 100;', $script);
        $this->assertStringContainsString('$page = 1;', $script);
        $this->assertStringContainsString('while (true)', $script);
        $this->assertStringContainsString('wp_cache_flush_runtime', $script);
        $this->assertStringContainsString('$page++;', $script);
    }

    public function test_compare_identifies_new_modified_deleted_and_identical_entities(): void
    {
        $source = [
            'SKU-001' => [
                'sku'         => 'SKU-001',
                'name'        => 'Camiseta Motta',
                'price'       => 25.0,
                'status'      => 'publish',
                'fingerprint' => 'fp_001_same',
            ],
            'SKU-002' => [
                'sku'         => 'SKU-002',
                'name'        => 'Pantalón Cargo',
                'price'       => 45.0,
                'status'      => 'publish',
                'fingerprint' => 'fp_002_new_hash',
            ],
            'SKU-003' => [
                'sku'         => 'SKU-003',
                'name'        => 'Gorra Urbana',
                'price'       => 15.0,
                'status'      => 'draft',
                'fingerprint' => 'fp_003_only_source',
            ],
        ];

        $target = [
            'SKU-001' => [
                'sku'         => 'SKU-001',
                'name'        => 'Camiseta Motta',
                'price'       => 25.0,
                'status'      => 'publish',
                'fingerprint' => 'fp_001_same',
            ],
            'SKU-002' => [
                'sku'         => 'SKU-002',
                'name'        => 'Pantalón Cargo',
                'price'       => 40.0, // Precio diferente
                'status'      => 'publish',
                'fingerprint' => 'fp_002_old_hash',
            ],
            'SKU-004' => [
                'sku'         => 'SKU-004',
                'name'        => 'Cinturón Cuero',
                'price'       => 10.0,
                'status'      => 'publish',
                'fingerprint' => 'fp_004_only_target',
            ],
        ];

        $diff = $this->comparator->compare($source, $target);

        $this->assertArrayHasKey('SKU-001', $diff['identical']);
        $this->assertArrayHasKey('SKU-002', $diff['modified']);
        $this->assertArrayHasKey('SKU-003', $diff['new']);
        $this->assertArrayHasKey('SKU-004', $diff['deleted']);

        $this->assertEquals(['from' => 40.0, 'to' => 45.0], $diff['modified']['SKU-002']['diffs']['price']);
    }

    public function test_compute_field_diff_detects_status_and_sale_price_changes(): void
    {
        $src = [
            'name'       => 'Producto A',
            'status'     => 'draft',
            'price'      => 30.0,
            'sale_price' => null, // Oferta eliminada
        ];

        $tgt = [
            'name'       => 'Producto A',
            'status'     => 'publish',
            'price'      => 20.0,
            'sale_price' => 20.0, // Oferta activa previa
        ];

        $diffs = $this->comparator->computeFieldDiff($src, $tgt);

        $this->assertArrayHasKey('status', $diffs);
        $this->assertEquals('publish', $diffs['status']['from']);
        $this->assertEquals('draft', $diffs['status']['to']);

        $this->assertArrayHasKey('sale_price', $diffs);
        $this->assertEquals(20.0, $diffs['sale_price']['from']);
        $this->assertNull($diffs['sale_price']['to']);
    }
}
