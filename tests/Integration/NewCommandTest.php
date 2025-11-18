<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Roots\BedrockCli\Commands\Setup\NewCommand;
use Roots\BedrockCli\Services\ProfileService;
use Roots\BedrockCli\Services\ComposerService;
use Roots\BedrockCli\Services\BlueprintService;
use Roots\BedrockCli\Services\AuthService;
use Roots\BedrockCli\Services\StateService;
use Roots\BedrockCli\Services\ProjectValidationService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NewCommandTest extends TestCase
{
    public function test_command_can_be_instantiated_with_dependencies(): void
    {
        $profileService = $this->createMock(ProfileService::class);
        $composerService = $this->createMock(ComposerService::class);
        $blueprintService = $this->createMock(BlueprintService::class);
        $authService = $this->createMock(AuthService::class);
        $stateService = $this->createMock(StateService::class);
        $validationService = $this->createMock(ProjectValidationService::class);

        $command = new NewCommand(
            $profileService,
            $composerService,
            $blueprintService,
            $authService,
            $stateService,
            $validationService
        );

        $this->assertInstanceOf(NewCommand::class, $command);
        $this->assertEquals('new', $command->getName());
    }

    public function test_command_configuration(): void
    {
        $profileService = $this->createMock(ProfileService::class);
        $composerService = $this->createMock(ComposerService::class);
        $blueprintService = $this->createMock(BlueprintService::class);
        $authService = $this->createMock(AuthService::class);
        $stateService = $this->createMock(StateService::class);
        $validationService = $this->createMock(ProjectValidationService::class);

        $command = new NewCommand(
            $profileService,
            $composerService,
            $blueprintService,
            $authService,
            $stateService,
            $validationService
        );

        $this->assertEquals('new', $command->getName());
        $this->assertEquals('Crear nuevo proyecto Bedrock', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasArgument('name'));
        $this->assertTrue($command->getDefinition()->hasOption('profile'));
    }
}