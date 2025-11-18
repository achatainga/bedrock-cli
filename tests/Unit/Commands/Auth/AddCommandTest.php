<?php

namespace Tests\Unit\Commands\Auth;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Auth\AddCommand;
use Symfony\Component\Console\Application;

class AddCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new AddCommand();
        
        $this->assertEquals('auth:add', $command->getName());
        $this->assertEquals('Agregar credenciales para repositorios privados', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('type'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new AddCommand());
        
        $this->assertTrue($application->has('auth:add'));
    }
}
