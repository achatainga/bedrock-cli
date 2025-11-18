<?php

namespace Tests\Unit\Commands\AI;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\AI\AICommand;
use Symfony\Component\Console\Application;

class AICommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new AICommand();
        
        $this->assertEquals('ai', $command->getName());
        $this->assertEquals('Menú principal de IA Copilot', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new AICommand());
        
        $this->assertTrue($application->has('ai'));
    }
}
