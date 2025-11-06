<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\ExportCommand;
use Symfony\Component\Console\Application;

class ExportCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ExportCommand();
        
        $this->assertEquals('profile:export', $command->getName());
        $this->assertEquals('Exportar profile desde un proyecto existente', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ExportCommand());
        
        $this->assertTrue($application->has('profile:export'));
    }
}
