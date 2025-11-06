<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\RemovePluginCommand;
use Symfony\Component\Console\Application;

class RemovePluginCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new RemovePluginCommand();
        
        $this->assertEquals('profile:remove-plugin', $command->getName());
        $this->assertEquals('Remover plugin de un profile', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('profile'));
        $this->assertTrue($command->getDefinition()->hasArgument('slug'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new RemovePluginCommand());
        
        $this->assertTrue($application->has('profile:remove-plugin'));
    }
}
