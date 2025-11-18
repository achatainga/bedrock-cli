<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\ListCommand;
use Symfony\Component\Console\Application;

class ListCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ListCommand();
        
        $this->assertEquals('profile:list', $command->getName());
        $this->assertEquals('Listar profiles disponibles', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ListCommand());
        
        $this->assertTrue($application->has('profile:list'));
    }
}
