<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\AddRepoCommand;
use Symfony\Component\Console\Application;

class AddRepoCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new AddRepoCommand();
        
        $this->assertEquals('profile:add-repo', $command->getName());
        $this->assertEquals('Agregar repositorio a un profile', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('profile'));
        $this->assertTrue($command->getDefinition()->hasOption('type'));
        $this->assertTrue($command->getDefinition()->hasOption('url'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new AddRepoCommand());
        
        $this->assertTrue($application->has('profile:add-repo'));
    }
}
