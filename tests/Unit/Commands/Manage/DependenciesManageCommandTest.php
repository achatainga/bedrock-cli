<?php

namespace Tests\Unit\Commands\Manage;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Manage\DependenciesManageCommand;
use Symfony\Component\Console\Application;

class DependenciesManageCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new DependenciesManageCommand();
        
        $this->assertEquals('manage:dependencies', $command->getName());
        $this->assertEquals('Gestión de dependencias Composer del proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new DependenciesManageCommand());
        
        $this->assertTrue($application->has('manage:dependencies'));
    }
}
