<?php

namespace Tests\Unit\Commands\Add;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Add\DependencyCommand;
use Symfony\Component\Console\Application;

class DependencyCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new DependencyCommand();
        
        $this->assertEquals('add:dependency', $command->getName());
        $this->assertEquals('Agregar dependencia Composer al proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new DependencyCommand());
        
        $this->assertTrue($application->has('add:dependency'));
    }
}
