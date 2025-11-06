<?php

namespace Tests\Unit\Commands\Auth;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Auth\ListCommand;
use Symfony\Component\Console\Application;

class ListCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ListCommand();
        
        $this->assertEquals('auth:list', $command->getName());
        $this->assertEquals('Listar credenciales configuradas', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ListCommand());
        
        $this->assertTrue($application->has('auth:list'));
    }
}
