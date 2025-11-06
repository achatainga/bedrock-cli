<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\HelperSet;

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

    public function test_it_returns_current_directory_when_is_bedrock_project(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);

        $composerJson = ['require' => ['roots/bedrock' => '^1.0']];
        file_put_contents($tempDir . '/composer.json', json_encode($composerJson));

        $originalDir = getcwd();
        chdir($tempDir);

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);

        $result = $this->ensureBedrockProject($input, $output);

        $this->assertEquals(realpath($tempDir), realpath($result));

        // Cleanup
        chdir($originalDir);
        unlink($tempDir . '/composer.json');
        rmdir($tempDir);
    }

    public function test_it_returns_null_when_no_projects_found(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);

        $originalDir = getcwd();
        chdir($tempDir);

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->atLeastOnce())->method('writeln');

        $result = $this->ensureBedrockProject($input, $output);

        $this->assertNull($result);

        // Cleanup
        chdir($originalDir);
        rmdir($tempDir);
    }

    public function test_it_auto_selects_single_project_found(): void
    {
        $tempDir = sys_get_temp_dir() . '/bedrock_test_' . uniqid();
        mkdir($tempDir);
        
        $projectDir = $tempDir . '/project1';
        mkdir($projectDir);
        $composerJson = ['require' => ['roots/bedrock' => '^1.0']];
        file_put_contents($projectDir . '/composer.json', json_encode($composerJson));

        $originalDir = getcwd();
        chdir($tempDir);

        $input = $this->createMock(InputInterface::class);
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->atLeastOnce())->method('writeln');

        $result = $this->ensureBedrockProject($input, $output);

        $this->assertEquals(realpath($projectDir), realpath($result));
        $this->assertEquals(realpath($projectDir), realpath(getcwd()));

        // Cleanup
        chdir($originalDir);
        unlink($projectDir . '/composer.json');
        rmdir($projectDir);
        rmdir($tempDir);
    }


}
