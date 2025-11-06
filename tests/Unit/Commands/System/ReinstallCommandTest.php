<?php

namespace Tests\Unit\Commands\System;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\ReinstallCommand;
use Symfony\Component\Console\Application;

class ReinstallCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ReinstallCommand();
        
        $this->assertEquals('reinstall', $command->getName());
        $this->assertEquals('Reinstalar WordPress', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ReinstallCommand());
        
        $this->assertTrue($application->has('reinstall'));
    }
}
