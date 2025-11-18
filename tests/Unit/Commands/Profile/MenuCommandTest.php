<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\MenuCommand;
use Symfony\Component\Console\Application;

class MenuCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new MenuCommand();
        
        $this->assertEquals('profile:menu', $command->getName());
        $this->assertEquals('Menú de gestión de profiles', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new MenuCommand());
        
        $this->assertTrue($application->has('profile:menu'));
    }
}
