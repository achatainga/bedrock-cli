<?php

namespace Tests\Unit\Commands\Plugins;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugins\MenuCommand;
use Symfony\Component\Console\Application;

class MenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new MenuCommand();
        
        $this->assertEquals('plugins', $command->getName());
        $this->assertEquals('Gestión de plugins', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new MenuCommand());
        
        $this->assertTrue($application->has('plugins'));
    }
}
