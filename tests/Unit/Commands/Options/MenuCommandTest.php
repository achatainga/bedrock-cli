<?php

namespace Tests\Unit\Commands\Options;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Options\MenuCommand;
use Symfony\Component\Console\Application;

class MenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new MenuCommand();
        
        $this->assertEquals('options', $command->getName());
        $this->assertEquals('Gestión de opciones de WordPress (wp_options)', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new MenuCommand());
        
        $this->assertTrue($application->has('options'));
    }
}
