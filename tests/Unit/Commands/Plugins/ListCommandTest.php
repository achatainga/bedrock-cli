<?php

namespace Tests\Unit\Commands\Plugins;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugins\ListCommand;
use Symfony\Component\Console\Application;

class ListCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ListCommand();
        
        $this->assertEquals('plugins:list', $command->getName());
        $this->assertEquals('Lista todos los plugins instalados', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ListCommand());
        
        $this->assertTrue($application->has('plugins:list'));
    }
}
