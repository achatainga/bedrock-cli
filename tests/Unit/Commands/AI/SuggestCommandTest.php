<?php

namespace Tests\Unit\Commands\AI;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\AI\SuggestCommand;
use Symfony\Component\Console\Application;

class SuggestCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new SuggestCommand();
        
        $this->assertEquals('ai:suggest', $command->getName());
        $this->assertEquals('Sugerencias inteligentes para el proyecto', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new SuggestCommand());
        
        $this->assertTrue($application->has('ai:suggest'));
    }
}
