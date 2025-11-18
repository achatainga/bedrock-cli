<?php

namespace Tests\Unit\Commands\Themes;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Themes\ActivateCommand;
use Symfony\Component\Console\Application;

class ActivateCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ActivateCommand();
        
        $this->assertEquals('themes:activate', $command->getName());
        $this->assertEquals('Activa un tema', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('theme'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ActivateCommand());
        
        $this->assertTrue($application->has('themes:activate'));
    }
}
