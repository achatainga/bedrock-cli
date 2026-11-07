<?php

namespace Tests\Unit\Commands\Options;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Options\PullCommand;
use Symfony\Component\Console\Application;

class PullCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new PullCommand();
        
        $this->assertEquals('options:pull', $command->getName());
        $this->assertEquals('Exportar opciones de WordPress a archivos JSON', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new PullCommand());
        
        $this->assertTrue($application->has('options:pull'));
    }

    public function testGeneratePullScriptUsesKeysetPagination(): void
    {
        $command = new class extends PullCommand {
            public function exposeScript(?string $prefix, bool $all): string {
                return $this->generatePullScript($prefix, $all, [], []);
            }
        };

        $scriptAll = $command->exposeScript(null, true);
        $this->assertStringContainsString('option_name > %s', $scriptAll);
        $this->assertStringContainsString('chunkSize = 1000', $scriptAll);
        $this->assertStringContainsString('ORDER BY option_name ASC LIMIT %d', $scriptAll);

        $scriptPrefix = $command->exposeScript('my_prefix_', false);
        $this->assertStringContainsString('option_name > %s', $scriptPrefix);
        $this->assertStringContainsString('WHERE option_name LIKE %s AND option_name > %s', $scriptPrefix);
    }
}
