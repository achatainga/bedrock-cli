<?php

namespace Tests\Unit\Commands\Themes;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Themes\StatusCommand;
use Symfony\Component\Console\Application;

class StatusCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new StatusCommand();
        
        $this->assertEquals('themes:status', $command->getName());
        $this->assertEquals('Muestra información de un tema', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new StatusCommand());
        
        $this->assertTrue($application->has('themes:status'));
    }
}
