<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class ProjectSelectorTraitTest extends TestCase
{
    use ProjectSelectorTrait;

    public function test_it_detects_a_valid_bedrock_project(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);

        $composerJson = [
            'require' => [
                'roots/bedrock' => '^1.0'
            ]
        ];

        file_put_contents(
            $tempDir . '/composer.json',
            json_encode($composerJson, JSON_PRETTY_PRINT)
        );

        $result = $this->isBedrockProject($tempDir);

        $this->assertTrue($result);

        // Cleanup
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function test_it_rejects_directory_without_composer_json(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);

        $result = $this->isBedrockProject($tempDir);

        $this->assertFalse($result);

        // Cleanup
        rmdir($tempDir);
    }

    public function test_it_rejects_project_without_bedrock_dependency(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);

        $composerJson = [
            'require' => [
                'symfony/console' => '^6.0'
            ]
        ];

        file_put_contents(
            $tempDir . '/composer.json',
            json_encode($composerJson, JSON_PRETTY_PRINT)
        );

        $result = $this->isBedrockProject($tempDir);

        $this->assertFalse($result);

        // Cleanup
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function test_it_detects_project_with_roots_wordpress(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);

        $composerJson = [
            'require' => [
                'roots/wordpress' => '^6.0'
            ]
        ];

        file_put_contents(
            $tempDir . '/composer.json',
            json_encode($composerJson, JSON_PRETTY_PRINT)
        );

        $result = $this->isBedrockProject($tempDir);

        $this->assertTrue($result);

        // Cleanup
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function test_it_detects_project_with_installer_paths(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);

        $composerJson = [
            'extra' => [
                'installer-paths' => [
                    'web/app/mu-plugins/{$name}/' => ['type:wordpress-muplugin']
                ]
            ]
        ];

        file_put_contents(
            $tempDir . '/composer.json',
            json_encode($composerJson, JSON_PRETTY_PRINT)
        );

        $result = $this->isBedrockProject($tempDir);

        $this->assertTrue($result);

        // Cleanup
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }
}
