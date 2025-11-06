<?php

namespace Tests\Unit\Commands\AI;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\AI\DiagnoseCommand;
use Symfony\Component\Console\Application;

class DiagnoseCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new DiagnoseCommand();
        
        $this->assertEquals('ai:diagnose', $command->getName());
        $this->assertEquals('Diagnóstico automático del proyecto con IA', $command->getDescription());
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new DiagnoseCommand());
        
        $this->assertTrue($application->has('ai:diagnose'));
    }
}
