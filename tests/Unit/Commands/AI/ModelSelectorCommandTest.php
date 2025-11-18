<?php

namespace Tests\Unit\Commands\AI;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\AI\ModelSelectorCommand;
use Symfony\Component\Console\Application;

class ModelSelectorCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ModelSelectorCommand();
        
        $this->assertEquals('ai:model', $command->getName());
        $this->assertEquals('Seleccionar modelo de IA', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ModelSelectorCommand());
        
        $this->assertTrue($application->has('ai:model'));
    }
}
