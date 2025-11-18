<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\SetThemeCommand;
use Symfony\Component\Console\Application;

class SetThemeCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new SetThemeCommand();
        
        $this->assertEquals('profile:set-theme', $command->getName());
        $this->assertEquals('Establecer theme de un profile', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('profile'));
        $this->assertTrue($command->getDefinition()->hasArgument('slug'));
        $this->assertTrue($command->getDefinition()->hasOption('theme-version'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new SetThemeCommand());
        
        $this->assertTrue($application->has('profile:set-theme'));
    }
}
