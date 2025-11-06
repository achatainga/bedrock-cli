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
        
        $this->assertEquals('export-config', $command->getName());
        $this->assertEquals('Exportar configuración', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ExportConfigCommand());
        
        $this->assertTrue($application->has('export-config'));
    }
}
