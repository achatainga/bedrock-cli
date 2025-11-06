<?php

namespace Tests\Unit\Commands\Plugins;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugins\OrderCommand;
use Symfony\Component\Console\Application;

class OrderCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new OrderCommand();
        
        $this->assertEquals('plugins:order', $command->getName());
        $this->assertEquals('Ordenar carga de plugins', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new OrderCommand());
        
        $this->assertTrue($application->has('plugins:order'));
    }
}
