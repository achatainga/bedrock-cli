<?php

namespace Tests\Unit\Commands\System;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\SeedCommand;
use Symfony\Component\Console\Application;

class SeedCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new SeedCommand();
        
        $this->assertEquals('seed', $command->getName());
        $this->assertEquals('Ejecutar seeders de base de datos', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new SeedCommand());
        
        $this->assertTrue($application->has('seed'));
    }
}
