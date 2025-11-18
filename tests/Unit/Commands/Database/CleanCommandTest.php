<?php

namespace Tests\Unit\Commands\Database;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Database\CleanCommand;
use Symfony\Component\Console\Application;

class CleanCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new CleanCommand();
        
        $this->assertEquals('db:clean', $command->getName());
        $this->assertEquals('Limpiar base de datos (multisite, prefix, opciones)', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('from-multisite'));
        $this->assertTrue($command->getDefinition()->hasOption('old-prefix'));
        $this->assertTrue($command->getDefinition()->hasOption('new-prefix'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new CleanCommand());
        
        $this->assertTrue($application->has('db:clean'));
    }
}
