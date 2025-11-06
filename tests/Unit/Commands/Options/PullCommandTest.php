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
}
