<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\WordPressApiService;

class WordPressApiServiceTest extends TestCase
{
    private WordPressApiService $service;
    private string $tempDir;
    private string $originalHome;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/wp_api_test_' . uniqid();
        mkdir($this->tempDir);
        
        $this->originalHome = getenv('HOME') ?: getenv('USERPROFILE');
        putenv('HOME=' . $this->tempDir);
        putenv('USERPROFILE=' . $this->tempDir);
        
        $this->service = new WordPressApiService();
    }

    protected function tearDown(): void
    {
        putenv('HOME=' . $this->originalHome);
        putenv('USERPROFILE=' . $this->originalHome);
        
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

    public function test_it_creates_cache_directory(): void
    {
        $cacheDir = $this->tempDir . '/.bedrock-cli/cache';
        $this->assertDirectoryExists($cacheDir);
    }

    public function test_it_initializes(): void
    {
        $this->assertInstanceOf(WordPressApiService::class, $this->service);
    }
}
