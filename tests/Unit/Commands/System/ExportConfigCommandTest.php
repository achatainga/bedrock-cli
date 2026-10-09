<?php

namespace Tests\Unit\Commands\System;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\ExportConfigCommand;
use Symfony\Component\Console\Application;

class ExportConfigCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ExportConfigCommand();
        
        $this->assertEquals('export:config', $command->getName());
        $this->assertEquals('Exportar configuración de WordPress a JSON', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ExportConfigCommand());
        
        $this->assertTrue($application->has('export:config'));
    }

    public function testGenerateExportScriptUsesKeysetPagination(): void
    {
        $command = new class extends ExportConfigCommand {
            public function exposeScript(bool $all): string {
                return $this->generateExportScript($all, []);
            }
        };

        $scriptAll = $command->exposeScript(true);
        $this->assertStringContainsString('option_name > %s', $scriptAll);
        $this->assertStringContainsString('chunkSize = 1000', $scriptAll);
        $this->assertStringContainsString('ORDER BY option_name ASC LIMIT %d', $scriptAll);
    }
}
