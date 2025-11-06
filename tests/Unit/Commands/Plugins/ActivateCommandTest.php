<?php

namespace Tests\Unit\Commands\Plugins;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugins\ActivateCommand;
use Symfony\Component\Console\Application;

class ActivateCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ActivateCommand();
        
        $this->assertEquals('plugins:activate', $command->getName());
        $this->assertEquals('Activa un plugin', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('plugin'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ActivateCommand());
        
        $this->assertTrue($application->has('plugins:activate'));
    }
}
