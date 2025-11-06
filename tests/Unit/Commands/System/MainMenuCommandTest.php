<?php

namespace Tests\Unit\Commands\System;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\MainMenuCommand;
use Symfony\Component\Console\Application;

class MainMenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new MainMenuCommand();
        
        $this->assertEquals('menu', $command->getName());
        $this->assertEquals('Menú principal', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new MainMenuCommand());
        
        $this->assertTrue($application->has('menu'));
    }
}
