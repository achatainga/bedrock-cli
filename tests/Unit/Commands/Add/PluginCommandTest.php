<?php

namespace Tests\Unit\Commands\Add;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Add\PluginCommand;
use Symfony\Component\Console\Application;

class PluginCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new PluginCommand();
        
        $this->assertEquals('add:plugin', $command->getName());
        $this->assertEquals('Agregar plugin al proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new PluginCommand());
        
        $this->assertTrue($application->has('add:plugin'));
    }
}
