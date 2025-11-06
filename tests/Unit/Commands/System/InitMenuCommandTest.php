<?php

namespace Tests\Unit\Commands\System;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\InitMenuCommand;
use Symfony\Component\Console\Application;

class InitMenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new InitMenuCommand();
        
        $this->assertEquals('init:menu', $command->getName());
        $this->assertEquals('Menú de inicialización de ambientes', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new InitMenuCommand());
        
        $this->assertTrue($application->has('init:menu'));
    }
}
