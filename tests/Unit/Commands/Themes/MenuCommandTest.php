<?php

namespace Tests\Unit\Commands\Themes;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Themes\MenuCommand;
use Symfony\Component\Console\Application;

class MenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new MenuCommand();
        
        $this->assertEquals('themes', $command->getName());
        $this->assertEquals('Gestión de temas', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new MenuCommand());
        
        $this->assertTrue($application->has('themes'));
    }
}
