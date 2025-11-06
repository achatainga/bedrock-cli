<?php

namespace Tests\Unit\Commands\AI;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\AI\ConfigCommand;
use Symfony\Component\Console\Application;

class ConfigCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ConfigCommand();
        
        $this->assertEquals('ai:config', $command->getName());
        $this->assertEquals('Configurar API keys para IA', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ConfigCommand());
        
        $this->assertTrue($application->has('ai:config'));
    }
}
