<?php

namespace Tests\Unit\Commands\Options;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Options\ManageCommand;
use Symfony\Component\Console\Application;

class ManageCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ManageCommand();
        
        $this->assertEquals('options:manage', $command->getName());
        $this->assertEquals('Gestionar opciones individuales (importar/exportar)', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ManageCommand());
        
        $this->assertTrue($application->has('options:manage'));
    }
}
