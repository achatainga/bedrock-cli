<?php

namespace Tests\Unit\Commands\Plugin;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugin\InfoCommand;
use Symfony\Component\Console\Application;

class InfoCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new InfoCommand();
        
        $this->assertEquals('plugin:info', $command->getName());
        $this->assertEquals('Get detailed information about a WordPress plugin', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new InfoCommand());
        
        $this->assertTrue($application->has('plugin:info'));
    }
}
