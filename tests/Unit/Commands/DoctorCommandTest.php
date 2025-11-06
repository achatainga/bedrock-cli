<?php

declare(strict_types=1);

namespace Tests\Unit\Commands;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\System\DoctorCommand;
use Symfony\Component\Console\Application;

class DoctorCommandTest extends TestCase
{
    public function test_command_is_configured(): void
    {
        $command = new DoctorCommand();
        
        $this->assertEquals('doctor', $command->getName());
        $this->assertNotEmpty($command->getDescription());
    }

    public function test_command_can_be_added_to_application(): void
    {
        $application = new Application();
        $command = new DoctorCommand();
        $application->add($command);
        
        $this->assertTrue($application->has('doctor'));
    }
}
