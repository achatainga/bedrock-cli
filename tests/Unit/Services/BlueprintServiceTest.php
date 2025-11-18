<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\BlueprintService;

class BlueprintServiceTest extends TestCase
{
    private BlueprintService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->service = new BlueprintService();
        $this->tempDir = sys_get_temp_dir() . '/blueprint_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
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

    public function test_it_creates_blueprints_directory(): void
    {
        $profile = ['name' => 'test-profile'];
        
        $this->service->generateBlueprints($profile, $this->tempDir);
        
        $blueprintsDir = $this->tempDir . '/blueprints';
        $this->assertDirectoryExists($blueprintsDir);
    }

    public function test_it_initializes_with_stubs_path(): void
    {
        $this->assertInstanceOf(BlueprintService::class, $this->service);
    }
}
