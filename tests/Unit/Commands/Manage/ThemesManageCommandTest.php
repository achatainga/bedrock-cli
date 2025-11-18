<?php

namespace Tests\Unit\Commands\Manage;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Manage\ThemesManageCommand;
use Symfony\Component\Console\Application;

class ThemesManageCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ThemesManageCommand();
        
        $this->assertEquals('manage:themes', $command->getName());
        $this->assertEquals('Gestión de themes del proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ThemesManageCommand());
        
        $this->assertTrue($application->has('manage:themes'));
    }
}
