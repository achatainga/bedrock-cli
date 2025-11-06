<?php

namespace Tests\Unit\Commands\Manage;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Manage\ManageCommand;
use Symfony\Component\Console\Application;

class ManageCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ManageCommand();
        
        $this->assertEquals('manage', $command->getName());
        $this->assertEquals('Sistema unificado de gestión de proyectos Bedrock', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ManageCommand());
        
        $this->assertTrue($application->has('manage'));
    }
}
