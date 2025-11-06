<?php

namespace Tests\Unit\Commands\Options;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Options\ListCommand;
use Symfony\Component\Console\Application;

class ListCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ListCommand();
        
        $this->assertEquals('options:list', $command->getName());
        $this->assertEquals('Listar archivos JSON de opciones disponibles', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ListCommand());
        
        $this->assertTrue($application->has('options:list'));
    }
}
