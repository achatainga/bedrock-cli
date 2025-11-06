<?php

namespace Tests\Unit\Commands\Auth;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Auth\RemoveCommand;
use Symfony\Component\Console\Application;

class RemoveCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new RemoveCommand();
        
        $this->assertEquals('auth:remove', $command->getName());
        $this->assertEquals('Eliminar credenciales configuradas', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new RemoveCommand());
        
        $this->assertTrue($application->has('auth:remove'));
    }
}
