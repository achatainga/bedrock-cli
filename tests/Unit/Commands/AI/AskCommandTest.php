<?php

namespace Tests\Unit\Commands\AI;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\AI\AskCommand;
use Symfony\Component\Console\Application;

class AskCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new AskCommand();
        
        $this->assertEquals('ai:ask', $command->getName());
        $this->assertEquals('Pregunta rápida a la IA', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('question'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new AskCommand());
        
        $this->assertTrue($application->has('ai:ask'));
    }
}
