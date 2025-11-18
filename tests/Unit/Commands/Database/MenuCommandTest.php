<?php

namespace Tests\Unit\Commands\Database;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Database\MenuCommand;
use Symfony\Component\Console\Application;

class MenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new MenuCommand();
        
        $this->assertEquals('db', $command->getName());
        $this->assertEquals('Gestión de base de datos', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('create'));
        $this->assertTrue($command->getDefinition()->hasOption('import'));
        $this->assertTrue($command->getDefinition()->hasOption('export'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new MenuCommand());
        
        $this->assertTrue($application->has('db'));
    }
}
