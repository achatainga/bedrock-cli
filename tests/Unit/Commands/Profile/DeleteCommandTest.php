<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\DeleteCommand;
use Symfony\Component\Console\Application;

class DeleteCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new DeleteCommand();
        
        $this->assertEquals('profile:delete', $command->getName());
        $this->assertEquals('Eliminar un profile', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new DeleteCommand());
        
        $this->assertTrue($application->has('profile:delete'));
    }
}
