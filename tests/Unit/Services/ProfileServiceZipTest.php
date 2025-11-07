<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\ProfileService;

class ProfileServiceZipTest extends TestCase
{
    private ProfileService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->service = new ProfileService();
        $this->tempDir = sys_get_temp_dir() . '/bedrock-cli-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }

    public function testScanCustomPluginsDetectsZipFile(): void
    {
        $zipPath = $this->tempDir . '/test-plugin.zip';
        file_put_contents($zipPath, 'fake zip content');

        $result = $this->service->scanCustomPlugins($zipPath);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('test-plugin', $result);
        $this->assertEquals('zip', $result['test-plugin']['source']);
        $this->assertEquals($zipPath, $result['test-plugin']['path']);
    }

    public function testScanCustomThemesDetectsZipFile(): void
    {
        $zipPath = $this->tempDir . '/test-theme.zip';
        file_put_contents($zipPath, 'fake zip content');

        $result = $this->service->scanCustomThemes($zipPath);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('test-theme', $result);
        $this->assertEquals('zip', $result['test-theme']['source']);
        $this->assertEquals($zipPath, $result['test-theme']['path']);
    }

    public function testScanCustomPluginsRejectsNonZipFile(): void
    {
        $txtPath = $this->tempDir . '/test.txt';
        file_put_contents($txtPath, 'not a zip');

        $this->expectException(\RuntimeException::class);
        $this->service->scanCustomPlugins($txtPath);
    }

    public function testScanCustomThemesRejectsNonZipFile(): void
    {
        $txtPath = $this->tempDir . '/test.txt';
        file_put_contents($txtPath, 'not a zip');

        $this->expectException(\RuntimeException::class);
        $this->service->scanCustomThemes($txtPath);
    }
}
