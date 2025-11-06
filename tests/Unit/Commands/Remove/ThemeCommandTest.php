<?php

namespace Tests\Unit\Commands\Remove;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Remove\ThemeCommand;
use Symfony\Component\Console\Application;

class ThemeCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ThemeCommand();
        
        $this->assertEquals('remove:theme', $command->getName());
        $this->assertEquals('Eliminar theme del proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ThemeCommand());
        
        $this->assertTrue($application->has('remove:theme'));
    }
}
