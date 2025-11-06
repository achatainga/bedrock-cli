<?php

namespace Tests\Unit\Commands\Remove;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Remove\DependencyCommand;
use Symfony\Component\Console\Application;

class DependencyCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new DependencyCommand();
        
        $this->assertEquals('remove:dependency', $command->getName());
        $this->assertEquals('Eliminar dependencia Composer del proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new DependencyCommand());
        
        $this->assertTrue($application->has('remove:dependency'));
    }
}
