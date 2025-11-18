<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Docker\DockerCommand;
use Symfony\Component\Console\Application;

class DockerCommandTest extends TestCase
{
    public function test_command_is_configured(): void
    {
        $command = new DockerCommand();
        
        $this->assertEquals('docker', $command->getName());
        $this->assertNotEmpty($command->getDescription());
    }

    public function test_command_can_be_added_to_application(): void
    {
        $application = new Application();
        $command = new DockerCommand();
        $application->add($command);
        
        $this->assertTrue($application->has('docker'));
    }
}
