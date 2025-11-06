<?php

namespace Tests\Unit\Commands\Options;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Options\PushCommand;
use Symfony\Component\Console\Application;

class PushCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new PushCommand();
        
        $this->assertEquals('options:push', $command->getName());
        $this->assertEquals('Importar opciones desde archivos JSON a WordPress', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new PushCommand());
        
        $this->assertTrue($application->has('options:push'));
    }
}
