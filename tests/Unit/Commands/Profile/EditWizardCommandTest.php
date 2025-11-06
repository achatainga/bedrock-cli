<?php

namespace Tests\Unit\Commands\Profile;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\EditWizardCommand;
use Symfony\Component\Console\Application;

class EditWizardCommandTest extends TestCase
{
    public function testCommandConfiguration(): void
    {
        $command = new EditWizardCommand();
        
        $this->assertEquals('profile:edit-wizard', $command->getName());
        $this->assertEquals('Editar profile existente con wizard interactivo', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
    }

    public function testCommandRegistration(): void
    {
        $application = new Application();
        $command = new EditWizardCommand();
        $application->add($command);
        
        $this->assertTrue($application->has('profile:edit-wizard'));
    }
}
