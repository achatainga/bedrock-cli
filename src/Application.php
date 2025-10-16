<?php

namespace Roots\BedrockCli;

use Symfony\Component\Console\Application as BaseApplication;
use Roots\BedrockCli\Commands\DockerCommand;
use Roots\BedrockCli\Commands\DatabaseCommand;
use Roots\BedrockCli\Commands\InstallCommand;
use Roots\BedrockCli\Commands\PluginsCommand;
use Roots\BedrockCli\Commands\ThemesCommand;
use Roots\BedrockCli\Commands\BackupCommand;
// use Roots\BedrockCli\Commands\UpdateCommand; // DESHABILITADO: Composer debe gestionar versiones
use Roots\BedrockCli\Commands\ReinstallCommand;
use Roots\BedrockCli\Commands\DoctorCommand;
use Roots\BedrockCli\Commands\MainMenuCommand;
use Roots\BedrockCli\Commands\SetupCommand;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('bedrock', '1.0.0');

        $this->addCommands([
            new MainMenuCommand(),
            new SetupCommand(),
            new DockerCommand(),
            new DatabaseCommand(),
            new InstallCommand(),
            new PluginsCommand(),
            new ThemesCommand(),
            new BackupCommand(),
            new ReinstallCommand(),
            new DoctorCommand(),
            // new UpdateCommand(), // DESHABILITADO: Composer debe gestionar versiones
        ]);
        
        $this->setDefaultCommand('main-menu', true);
    }
}
