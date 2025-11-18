<?php

namespace Tests\Unit\Commands\Themes;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Themes\CompressCommand;
use Symfony\Component\Console\Application;

class CompressCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new CompressCommand();
        
        $this->assertEquals('themes:compress', $command->getName());
        $this->assertEquals('Comprime un tema a ZIP', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new CompressCommand());
        
        $this->assertTrue($application->has('themes:compress'));
    }
}
