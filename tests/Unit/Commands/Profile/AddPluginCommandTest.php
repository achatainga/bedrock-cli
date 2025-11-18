<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\AddPluginCommand;
use Symfony\Component\Console\Application;

class AddPluginCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new AddPluginCommand();
        
        $this->assertEquals('profile:add-plugin', $command->getName());
        $this->assertEquals('Agregar plugin a un profile', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('profile'));
        $this->assertTrue($command->getDefinition()->hasArgument('slug'));
        $this->assertTrue($command->getDefinition()->hasOption('plugin-version'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new AddPluginCommand());
        
        $this->assertTrue($application->has('profile:add-plugin'));
    }
}
