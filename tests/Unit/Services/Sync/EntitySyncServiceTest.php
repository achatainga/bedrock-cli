<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sync;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\Sync\EntityComparatorService;
use Roots\BedrockCli\Services\Sync\EntitySyncService;

class EntitySyncServiceTest extends TestCase
{
    private EntitySyncService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->service = new EntitySyncService(new EntityComparatorService());
        $this->tempDir = sys_get_temp_dir() . '/sync_svc_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->removeDir($p) : unlink($p);
        }
        rmdir($dir);
    }

    public function test_stage_and_commit_changeset(): void
    {
        $entities = [
            'SKU-TEST-1' => [
                'sku'    => 'SKU-TEST-1',
                'name'   => 'Producto Prueba 1',
                'price'  => 19.99,
                'status' => 'publish',
            ],
        ];

        $stagedCount = $this->service->stageEntities($this->tempDir, $entities);
        $this->assertEquals(1, $stagedCount);

        $staged = $this->service->getStaged($this->tempDir);
        $this->assertArrayHasKey('SKU-TEST-1', $staged);

        $manifest = $this->service->commitChangeset($this->tempDir, 'Test changeset', 'tester');
        $this->assertNotEmpty($manifest['id']);
        $this->assertEquals('Test changeset', $manifest['message']);
        $this->assertEquals(1, $manifest['count']);

        // Staged debe estar limpio tras commit
        $this->assertEmpty($this->service->getStaged($this->tempDir));

        $changesets = $this->service->listChangesets($this->tempDir);
        $this->assertCount(1, $changesets);

        $loaded = $this->service->loadChangeset($this->tempDir, $manifest['id']);
        $this->assertNotNull($loaded);
        $this->assertEquals($manifest['id'], $loaded['id']);
    }

    public function test_generate_injection_script_includes_status_and_null_safe_attributes(): void
    {
        $entities = [
            'SKU-100' => [
                'sku'               => 'SKU-100',
                'name'              => 'Reloj Inteligente',
                'status'            => 'draft',
                'regular_price'     => 120.0,
                'sale_price'        => null, // Oferta removida
                'price'             => 120.0,
                'short_description' => '',   // Descripción vaciada
                'categories'        => [
                    ['slug' => 'tecnologia', 'name' => 'Tecnología'],
                ],
            ],
        ];

        $script = $this->service->generateInjectionScript($entities);

        // Verificar que decodifica payload base64
        $this->assertStringContainsString('base64_decode', $script);

        // Verificar que actualiza status
        $this->assertStringContainsString('$product->set_status(', $script);

        // Verificar que maneja sale_price nulo o blanqueado
        $this->assertStringContainsString("array_key_exists('sale_price'", $script);
        $this->assertStringContainsString('$product->set_sale_price(', $script);

        // Verificar que maneja cadenas vacías de descripción
        $this->assertStringContainsString("array_key_exists('short_description'", $script);
        $this->assertStringContainsString('$product->set_short_description(', $script);
    }

    public function test_generate_rollback_script_deletes_created_and_restores_saved(): void
    {
        $savedState = [
            'SKU-MOD-1' => [
                'sku'   => 'SKU-MOD-1',
                'name'  => 'Nombre Original',
                'price' => 50.0,
            ],
        ];

        $createdEntities = [
            ['sku' => 'SKU-NEW-1', 'id' => 999],
        ];

        $script = $this->service->generateRollbackScript($savedState, $createdEntities);

        // Debe contener código para eliminar entidades nuevas
        $this->assertStringContainsString('wp_delete_post(', $script);
        $this->assertStringContainsString('$applied[\'deleted\']', $script);

        // Debe contener código para restaurar entidades modificadas
        $this->assertStringContainsString('$product->save()', $script);
        $this->assertStringContainsString('$applied[\'restored\']', $script);
    }
}
