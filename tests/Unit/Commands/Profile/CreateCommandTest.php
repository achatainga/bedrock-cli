<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\CreateCommand;
use Symfony\Component\Console\Application;

class CreateCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new CreateCommand();
        
        $this->assertEquals('profile:create', $command->getName());
        $this->assertEquals('Crear un nuevo profile de proyecto', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new CreateCommand());
        
        $this->assertTrue($application->has('profile:create'));
    }
}
