<?php

namespace Tests\Unit\Commands\Add;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Add\ThemeCommand;
use Symfony\Component\Console\Application;

class ThemeCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ThemeCommand();
        
        $this->assertEquals('add:theme', $command->getName());
        $this->assertEquals('Agregar theme al proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ThemeCommand());
        
        $this->assertTrue($application->has('add:theme'));
    }
}
