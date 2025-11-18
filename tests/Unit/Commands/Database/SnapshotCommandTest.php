<?php

namespace Tests\Unit\Commands\Database;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Database\SnapshotCommand;
use Symfony\Component\Console\Application;

class SnapshotCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new SnapshotCommand();
        
        $this->assertEquals('snapshot', $command->getName());
        $this->assertEquals('Gestionar snapshots de base de datos', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('create'));
        $this->assertTrue($command->getDefinition()->hasOption('restore'));
        $this->assertTrue($command->getDefinition()->hasOption('name'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new SnapshotCommand());
        
        $this->assertTrue($application->has('snapshot'));
    }
}
