<?php

namespace Tests\Unit\Commands\Manage;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Manage\PluginsManageCommand;
use Symfony\Component\Console\Application;

class PluginsManageCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new PluginsManageCommand();
        
        $this->assertEquals('manage:plugins', $command->getName());
        $this->assertEquals('Gestión de plugins del proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new PluginsManageCommand());
        
        $this->assertTrue($application->has('manage:plugins'));
    }
}
