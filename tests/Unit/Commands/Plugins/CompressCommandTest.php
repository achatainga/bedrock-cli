<?php

namespace Tests\Unit\Commands\Plugins;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Plugins\CompressCommand;
use Symfony\Component\Console\Application;

class CompressCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new CompressCommand();
        
        $this->assertEquals('plugins:compress', $command->getName());
        $this->assertEquals('Comprimir plugins', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new CompressCommand());
        
        $this->assertTrue($application->has('plugins:compress'));
    }
}
