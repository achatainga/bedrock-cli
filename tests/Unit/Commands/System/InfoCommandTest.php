<?php

namespace Tests\Unit\Commands\System;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\InfoCommand;
use Symfony\Component\Console\Application;

class InfoCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new InfoCommand();
        
        $this->assertEquals('info', $command->getName());
        $this->assertEquals('Mostrar información del proyecto y estado actual', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new InfoCommand());
        
        $this->assertTrue($application->has('info'));
    }
}
