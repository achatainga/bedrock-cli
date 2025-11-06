<?php

namespace Tests\Unit\Commands\Plugins;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugins\DeactivateCommand;
use Symfony\Component\Console\Application;

class DeactivateCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new DeactivateCommand();
        
        $this->assertEquals('plugins:deactivate', $command->getName());
        $this->assertEquals('Desactiva un plugin', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new DeactivateCommand());
        
        $this->assertTrue($application->has('plugins:deactivate'));
    }
}
