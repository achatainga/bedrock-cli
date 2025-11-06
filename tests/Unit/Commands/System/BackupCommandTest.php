<?php

namespace Tests\Unit\Commands\System;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\BackupCommand;
use Symfony\Component\Console\Application;

class BackupCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new BackupCommand();
        
        $this->assertEquals('backup', $command->getName());
        $this->assertEquals('Crear backup completo', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new BackupCommand());
        
        $this->assertTrue($application->has('backup'));
    }
}
