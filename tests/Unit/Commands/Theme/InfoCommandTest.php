<?php

namespace Tests\Unit\Commands\Theme;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Theme\InfoCommand;
use Symfony\Component\Console\Application;

class InfoCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new InfoCommand();
        
        $this->assertEquals('theme:info', $command->getName());
        $this->assertEquals('Ver información de un tema', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new InfoCommand());
        
        $this->assertTrue($application->has('theme:info'));
    }
}
