<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\AIService;

class AIServiceTest extends TestCase
{
    private string $tempDir;
    private string $originalHome;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ai_test_' . uniqid();
        mkdir($this->tempDir);
        
        $this->originalHome = getenv('HOME') ?: getenv('USERPROFILE');
        putenv('HOME=' . $this->tempDir);
        putenv('USERPROFILE=' . $this->tempDir);
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

    public function test_it_initializes_with_default_config(): void
    {
        $service = new AIService([]);
        
        $this->assertInstanceOf(AIService::class, $service);
    }

    public function test_it_initializes_with_custom_config(): void
    {
        $config = [
            'provider' => 'gemini',
            'api_key' => 'test-key',
            'model' => 'gemini-2.5-flash'
        ];
        
        $service = new AIService($config);
        
        $this->assertInstanceOf(AIService::class, $service);
    }

    public function test_it_saves_config(): void
    {
        $service = new AIService([]);
        
        $config = [
            'provider' => 'openrouter',
            'api_key' => 'test-api-key',
            'model' => 'deepseek/deepseek-chat-v3.1:free'
        ];
        
        $service->saveConfig($config);
        
        $configPath = $this->tempDir . DIRECTORY_SEPARATOR . '.bedrock' . DIRECTORY_SEPARATOR . 'ai_config.json';
        $this->assertFileExists($configPath);
        
        $saved = json_decode(file_get_contents($configPath), true);
        $this->assertEquals('openrouter', $saved['provider']);
        $this->assertEquals('test-api-key', $saved['api_key']);
    }

    public function test_it_checks_if_configured(): void
    {
        $serviceNotConfigured = new AIService(['api_key' => '']);
        $this->assertFalse($serviceNotConfigured->isConfigured());
        
        $serviceConfigured = new AIService(['api_key' => 'test-key']);
        $this->assertTrue($serviceConfigured->isConfigured());
    }

    public function test_it_loads_config_from_file(): void
    {
        $configDir = $this->tempDir . DIRECTORY_SEPARATOR . '.bedrock';
        mkdir($configDir, 0755, true);
        
        $config = [
            'provider' => 'gemini',
            'api_key' => 'saved-key',
            'model' => 'gemini-2.5-flash'
        ];
        
        file_put_contents(
            $configDir . DIRECTORY_SEPARATOR . 'ai_config.json',
            json_encode($config)
        );
        
        $service = new AIService();
        
        $this->assertTrue($service->isConfigured());
    }

    public function test_it_throws_exception_for_unsupported_provider(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Provider 'unsupported' no soportado");
        
        $service = new AIService([
            'provider' => 'unsupported',
            'api_key' => 'test-key'
        ]);
        
        $service->ask('test question');
    }
}
