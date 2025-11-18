<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\ApplyCommand;
use Symfony\Component\Console\Application;

class ApplyCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ApplyCommand();
        
        $this->assertEquals('profile:apply', $command->getName());
        $this->assertEquals('Aplicar un profile a un proyecto existente', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ApplyCommand());
        
        $this->assertTrue($application->has('profile:apply'));
    }
}
