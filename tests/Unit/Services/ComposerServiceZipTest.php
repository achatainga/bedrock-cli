<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\ComposerService;

class ComposerServiceZipTest extends TestCase
{
    private ComposerService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->service = new ComposerService();
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

    public function testCopyProfileToProjectCreatesPluginsDirectory(): void
    {
        $zipPath = $this->tempDir . '/source-plugin.zip';
        file_put_contents($zipPath, 'fake zip');

        $profile = [
            'name' => 'test',
            'plugins' => [
                'custom' => [
                    ['slug' => 'test-plugin', 'source' => 'zip', 'path' => $zipPath]
                ]
            ]
        ];

        $projectPath = $this->tempDir . '/project';
        mkdir($projectPath, 0755, true);

        $this->service->copyProfileToProject($profile, $projectPath);

        $this->assertDirectoryExists($projectPath . '/plugins');
        $this->assertFileExists($projectPath . '/plugins/.gitkeep');
        $this->assertFileExists($projectPath . '/plugins/.gitignore');
        $this->assertFileExists($projectPath . '/plugins/source-plugin.zip');
    }

    public function testCopyProfileToProjectCreatesThemesDirectory(): void
    {
        $zipPath = $this->tempDir . '/source-theme.zip';
        file_put_contents($zipPath, 'fake zip');

        $profile = [
            'name' => 'test',
            'themes' => [
                'custom' => [
                    ['slug' => 'test-theme', 'source' => 'zip', 'path' => $zipPath]
                ]
            ]
        ];

        $projectPath = $this->tempDir . '/project';
        mkdir($projectPath, 0755, true);

        $this->service->copyProfileToProject($profile, $projectPath);

        $this->assertDirectoryExists($projectPath . '/themes');
        $this->assertFileExists($projectPath . '/themes/.gitkeep');
        $this->assertFileExists($projectPath . '/themes/.gitignore');
        $this->assertFileExists($projectPath . '/themes/source-theme.zip');
    }

    public function testGitignoreContentIsCorrect(): void
    {
        $zipPath = $this->tempDir . '/plugin.zip';
        file_put_contents($zipPath, 'fake');

        $profile = [
            'name' => 'test',
            'plugins' => [
                'custom' => [
                    ['slug' => 'test', 'source' => 'zip', 'path' => $zipPath]
                ]
            ]
        ];

        $projectPath = $this->tempDir . '/project';
        mkdir($projectPath, 0755, true);

        $this->service->copyProfileToProject($profile, $projectPath);

        $gitignoreContent = file_get_contents($projectPath . '/plugins/.gitignore');
        $this->assertStringContainsString('*', $gitignoreContent);
        $this->assertStringContainsString('!.gitkeep', $gitignoreContent);
        $this->assertStringContainsString('!.gitignore', $gitignoreContent);
    }
}
