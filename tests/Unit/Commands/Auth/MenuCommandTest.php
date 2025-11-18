<?php

namespace Tests\Unit\Commands\Auth;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Auth\MenuCommand;
use Symfony\Component\Console\Application;

class MenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new MenuCommand();
        
        $this->assertEquals('auth:menu', $command->getName());
        $this->assertEquals('Menú de gestión de autenticación', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new MenuCommand());
        
        $this->assertTrue($application->has('auth:menu'));
    }
}
