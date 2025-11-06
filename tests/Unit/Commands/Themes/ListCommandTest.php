<?php

namespace Tests\Unit\Commands\Themes;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Themes\ListCommand;
use Symfony\Component\Console\Application;

class ListCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ListCommand();
        
        $this->assertEquals('themes:list', $command->getName());
        $this->assertEquals('Lista todos los temas instalados', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ListCommand());
        
        $this->assertTrue($application->has('themes:list'));
    }
}
