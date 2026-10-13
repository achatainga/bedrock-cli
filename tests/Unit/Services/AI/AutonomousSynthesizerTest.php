<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Services\AI\AutonomousSynthesizer;

class AutonomousSynthesizerTest extends TestCase
{
    private AutonomousSynthesizer $synthesizer;

    protected function setUp(): void
    {
        $this->synthesizer = new AutonomousSynthesizer();
    }

    public function test_it_synthesizes_dt24_pure_php_plugin(): void
    {
        $result = $this->synthesizer->synthesizeDt24Plugin('analytics-hub', 'Analytics Hub', 'High performance tracking');

        $this->assertSame('analytics-hub', $result['slug']);
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('analytics-hub.php', $result['files']);
        $this->assertArrayHasKey('src/Plugin.php', $result['files']);

        $mainCode = $result['files']['analytics-hub.php'];
        $this->assertStringContainsString('declare(strict_types=1);', $mainCode);
        $this->assertStringContainsString('DT24-CORE-STANDARD-v4.0', $result['files']['README.md']);
        $this->assertStringContainsString('Requires PHP:      8.3', $mainCode);
    }

    public function test_it_synthesizes_acorn_service(): void
    {
        $result = $this->synthesizer->synthesizeAcornService('TelemetryWorker');

        $this->assertArrayHasKey('provider', $result);
        $this->assertArrayHasKey('service', $result);
        $this->assertStringContainsString('TelemetryWorkerServiceProvider', $result['provider']['code']);
        $this->assertStringContainsString('TelemetryWorkerService', $result['service']['code']);
    }
}
