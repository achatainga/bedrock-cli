<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\ShowCommand;
use Symfony\Component\Console\Application;

class ShowCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ShowCommand();
        
        $this->assertEquals('profile:show', $command->getName());
        $this->assertEquals('Mostrar detalles de un profile', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ShowCommand());
        
        $this->assertTrue($application->has('profile:show'));
    }
}
