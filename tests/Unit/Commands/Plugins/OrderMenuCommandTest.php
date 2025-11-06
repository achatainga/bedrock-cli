<?php

namespace Tests\Unit\Commands\Plugins;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugins\OrderMenuCommand;
use Symfony\Component\Console\Application;

class OrderMenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new OrderMenuCommand();
        
        $this->assertEquals('plugins:order-menu', $command->getName());
        $this->assertEquals('Menú de orden de plugins', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new OrderMenuCommand());
        
        $this->assertTrue($application->has('plugins:order-menu'));
    }
}
