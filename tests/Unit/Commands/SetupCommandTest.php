<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Setup\SetupCommand;
use Symfony\Component\Console\Application;

class SetupCommandTest extends TestCase
{
    public function test_command_is_configured(): void
    {
        $command = new SetupCommand();
        
        $this->assertEquals('setup', $command->getName());
        $this->assertNotEmpty($command->getDescription());
    }

    public function test_command_can_be_added_to_application(): void
    {
        $application = new Application();
        $command = new SetupCommand();
        $application->add($command);
        
        $this->assertTrue($application->has('setup'));
    }
}
