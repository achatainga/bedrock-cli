<?php

namespace Tests\Unit\Commands\Remove;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Remove\PluginCommand;
use Symfony\Component\Console\Application;

class PluginCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new PluginCommand();
        
        $this->assertEquals('remove:plugin', $command->getName());
        $this->assertEquals('Eliminar plugin del proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new PluginCommand());
        
        $this->assertTrue($application->has('remove:plugin'));
    }
}
