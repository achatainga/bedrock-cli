<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Setup\NewCommand;

class NewCommandTest extends TestCase
{
    public function test_command_can_be_instantiated_with_dependencies(): void
    {
        $command = new NewCommand();

        $this->assertInstanceOf(NewCommand::class, $command);
        $this->assertEquals('new', $command->getName());
    }

    public function test_command_configuration(): void
    {
        $command = new NewCommand();

        $this->assertEquals('new', $command->getName());
        $this->assertEquals('Crear nuevo proyecto Bedrock', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
    }
}
