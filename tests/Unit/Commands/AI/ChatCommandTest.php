<?php

namespace Tests\Unit\Commands\AI;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\AI\ChatCommand;
use Symfony\Component\Console\Application;

class ChatCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new ChatCommand();
        
        $this->assertEquals('ai:chat', $command->getName());
        $this->assertEquals('Chat interactivo con IA', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new ChatCommand());
        
        $this->assertTrue($application->has('ai:chat'));
    }
}
