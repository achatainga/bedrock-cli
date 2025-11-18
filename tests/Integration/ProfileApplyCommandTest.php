<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Profile\ApplyCommand;
use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\ComposerService;
use Roots\BedrockCli\Services\VcsValidator;

class ProfileApplyCommandTest extends TestCase
{
    public function test_command_can_be_instantiated_with_dependencies(): void
    {
        $profileService = $this->createMock(ProfileService::class);
        $composerService = $this->createMock(ComposerService::class);
        $vcsValidator = $this->createMock(VcsValidator::class);

        $command = new ApplyCommand(
            $profileService,
            $composerService,
            $vcsValidator
        );

        $this->assertInstanceOf(ApplyCommand::class, $command);
        $this->assertEquals('profile:apply', $command->getName());
    }

    public function test_command_configuration(): void
    {
        $profileService = $this->createMock(ProfileService::class);
        $composerService = $this->createMock(ComposerService::class);
        $vcsValidator = $this->createMock(VcsValidator::class);

        $command = new ApplyCommand(
            $profileService,
            $composerService,
            $vcsValidator
        );

        $this->assertEquals('profile:apply', $command->getName());
        $this->assertEquals('Aplicar un profile a un proyecto existente', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('yes'));
    }
}