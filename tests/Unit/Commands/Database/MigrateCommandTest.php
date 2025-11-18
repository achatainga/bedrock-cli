<?php

namespace Tests\Unit\Commands\Database;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Database\MigrateCommand;
use Symfony\Component\Console\Application;

class MigrateCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new MigrateCommand();
        
        $this->assertEquals('migrate', $command->getName());
        $this->assertEquals('Migrar base de datos desde SQL dump', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('sql-file'));
        $this->assertTrue($command->getDefinition()->hasOption('old-url'));
        $this->assertTrue($command->getDefinition()->hasOption('new-url'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new MigrateCommand());
        
        $this->assertTrue($application->has('migrate'));
    }
}
