<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\EditCommand;
use Symfony\Component\Console\Application;

class EditCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new EditCommand();
        
        $this->assertEquals('profile:edit', $command->getName());
        $this->assertEquals('Editar un profile en el editor del sistema', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $application->add(new EditCommand());
        
        $this->assertTrue($application->has('profile:edit'));
    }
}
