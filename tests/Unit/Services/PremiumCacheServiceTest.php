<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\PremiumCacheService;
use RuntimeException;

class PremiumCacheServiceTest extends TestCase
{
    private PremiumCacheService $service;
    private string $testZipsDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PremiumCacheService();
        $this->testZipsDir = __DIR__ . '/../../fixtures/zips';
    }

    public function testExtractMetadataFromComposerJson(): void
    {
        $zipPath = $this->testZipsDir . '/plugin-with-composer.zip';
        
        if (!file_exists($zipPath)) {
            $this->markTestSkipped('Test zip not available');
        }

        $metadata = $this->service->extractMetadataFromZip($zipPath, 'plugin');

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('version', $metadata);
        $this->assertArrayHasKey('source', $metadata);
        $this->assertEquals('composer.json', $metadata['source']);
    }

    public function testExtractMetadataFromStyleCss(): void
    {
        $zipPath = $this->testZipsDir . '/theme-with-style.zip';
        
        if (!file_exists($zipPath)) {
            $this->markTestSkipped('Test zip not available');
        }

        $metadata = $this->service->extractMetadataFromZip($zipPath, 'theme');

        $this->assertIsArray($metadata);
        $this->assertNotNull($metadata['name']);
        $this->assertNotNull($metadata['version']);
        $this->assertEquals('style.css', $metadata['source']);
    }

    public function testExtractMetadataFromPluginHeaders(): void
    {
        $zipPath = $this->testZipsDir . '/plugin-with-headers.zip';
        
        if (!file_exists($zipPath)) {
            $this->markTestSkipped('Test zip not available');
        }

        $metadata = $this->service->extractMetadataFromZip($zipPath, 'plugin');

        $this->assertIsArray($metadata);
        $this->assertNotNull($metadata['name']);
        $this->assertNotNull($metadata['version']);
        $this->assertStringEndsWith('.php', $metadata['source']);
    }

    public function testExtractMetadataReturnsNullWhenNotFound(): void
    {
        $zipPath = $this->testZipsDir . '/empty.zip';
        
        if (!file_exists($zipPath)) {
            $this->markTestSkipped('Test zip not available');
        }

        $metadata = $this->service->extractMetadataFromZip($zipPath, 'plugin');

        $this->assertIsArray($metadata);
        $this->assertNull($metadata['version']);
    }

    public function testExtractMetadataThrowsExceptionForInvalidZip(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        $this->service->extractMetadataFromZip('/nonexistent/file.zip', 'plugin');
    }

    public function testImportToCacheCreatesStructure(): void
    {
        $this->markTestIncomplete('Requires mock filesystem or temp directory');
    }

    public function testImportToCacheGeneratesComposerJson(): void
    {
        $this->markTestIncomplete('Requires mock filesystem or temp directory');
    }

    public function testCacheLocalFileRejectsZipSlip(): void
    {
        if (!class_exists('ZipArchive')) {
            $this->markTestSkipped('ZipArchive extension not available');
        }

        $tempDir = sys_get_temp_dir() . '/zip_slip_test_' . uniqid();
        mkdir($tempDir, 0755, true);
        $zipPath = $tempDir . '/malicious.zip';

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('../../../evil.txt', 'malicious content');
        $zip->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Zip Slip');

        try {
            $this->service->cacheLocalFile($zipPath, 'test-plugin', '1.0.0', 'plugin');
        } finally {
            @unlink($zipPath);
            @rmdir($tempDir);
        }
    }
}
