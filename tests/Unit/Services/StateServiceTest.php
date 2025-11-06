<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\StateService;

class StateServiceTest extends TestCase
{
    private StateService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->service = new StateService();
        $this->tempDir = sys_get_temp_dir() . '/state_test_' . uniqid();
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

    public function test_it_generates_initial_state(): void
    {
        $config = [
            'http_port' => '8080',
            'has_plugins' => true,
            'has_theme' => true,
            'has_acorn' => false
        ];

        $this->service->generateInitialState($this->tempDir, $config);

        $stateFile = $this->tempDir . '/bedrock_state.json';
        $this->assertFileExists($stateFile);

        $state = json_decode(file_get_contents($stateFile), true);
        $this->assertArrayHasKey('version', $state);
        $this->assertArrayHasKey('project_name', $state);
        $this->assertArrayHasKey('steps', $state);
        $this->assertEquals(basename($this->tempDir), $state['project_name']);
    }

    public function test_it_loads_existing_state(): void
    {
        $stateData = [
            'version' => '1.0',
            'project_name' => 'test-project',
            'current_step' => 1,
            'wizard_mode' => true,
            'steps' => []
        ];

        file_put_contents(
            $this->tempDir . '/bedrock_state.json',
            json_encode($stateData)
        );

        $loaded = $this->service->loadState($this->tempDir);

        $this->assertNotNull($loaded);
        $this->assertEquals('test-project', $loaded['project_name']);
        $this->assertTrue($loaded['wizard_mode']);
    }

    public function test_it_returns_null_when_state_does_not_exist(): void
    {
        $result = $this->service->loadState($this->tempDir);
        $this->assertNull($result);
    }

    public function test_it_marks_step_as_completed(): void
    {
        $stateData = [
            'version' => '1.0',
            'current_step' => 1,
            'wizard_mode' => true,
            'steps' => [
                ['id' => 1, 'title' => 'Step 1', 'completed' => false, 'skippable' => false],
                ['id' => 2, 'title' => 'Step 2', 'completed' => false, 'skippable' => false]
            ]
        ];

        file_put_contents(
            $this->tempDir . '/bedrock_state.json',
            json_encode($stateData)
        );

        $this->service->markCompleted($this->tempDir, 1);

        $updated = json_decode(file_get_contents($this->tempDir . '/bedrock_state.json'), true);
        
        $this->assertArrayHasKey('steps', $updated);
        $this->assertArrayHasKey('current_step', $updated);
        $this->assertEquals(2, $updated['current_step']);
    }

    public function test_it_disables_wizard_mode_when_all_steps_completed(): void
    {
        $stateData = [
            'version' => '1.0',
            'current_step' => 2,
            'wizard_mode' => true,
            'steps' => [
                ['id' => 1, 'title' => 'Step 1', 'completed' => true, 'skippable' => false],
                ['id' => 2, 'title' => 'Step 2', 'completed' => false, 'skippable' => false]
            ]
        ];

        file_put_contents(
            $this->tempDir . '/bedrock_state.json',
            json_encode($stateData)
        );

        $this->service->markCompleted($this->tempDir, 2);

        $updated = json_decode(file_get_contents($this->tempDir . '/bedrock_state.json'), true);
        
        $this->assertFalse($updated['wizard_mode']);
    }

    public function test_it_gets_current_step(): void
    {
        $state = [
            'current_step' => 2,
            'steps' => [
                ['id' => 1, 'title' => 'Step 1'],
                ['id' => 2, 'title' => 'Step 2'],
                ['id' => 3, 'title' => 'Step 3']
            ]
        ];

        $currentStep = $this->service->getCurrentStep($state);

        $this->assertNotNull($currentStep);
        $this->assertEquals(2, $currentStep['id']);
        $this->assertEquals('Step 2', $currentStep['title']);
    }

    public function test_it_returns_null_when_current_step_not_found(): void
    {
        $state = [
            'current_step' => 99,
            'steps' => [
                ['id' => 1, 'title' => 'Step 1']
            ]
        ];

        $currentStep = $this->service->getCurrentStep($state);

        $this->assertNull($currentStep);
    }
}
