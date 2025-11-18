<?php

namespace Tests\Unit\Commands\Plugins;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugins\StatusCommand;
use Symfony\Component\Console\Application;

class StatusCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new StatusCommand();
        
        $this->assertEquals('plugins:status', $command->getName());
        $this->assertEquals('Muestra información de un plugin', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new StatusCommand());
        
        $this->assertTrue($application->has('plugins:status'));
    }
}
