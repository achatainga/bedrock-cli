<?php

namespace Roots\BedrockCli;

use Symfony\Component\Console\Application as BaseApplication;
use Roots\BedrockCli\Commands\DockerCommand;
use Roots\BedrockCli\Commands\DatabaseCommand;
use Roots\BedrockCli\Commands\InstallCommand;
use Roots\BedrockCli\Commands\PluginsCommand;
use Roots\BedrockCli\Commands\ThemesCommand;
use Roots\BedrockCli\Commands\BackupCommand;
use Roots\BedrockCli\Commands\UpdateCommand;
use Roots\BedrockCli\Commands\MainMenuCommand;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('bedrock', '1.0.0');

        $this->addCommands([
            new MainMenuCommand(),
            new DockerCommand(),
            new DatabaseCommand(),
            new InstallCommand(),
            new PluginsCommand(),
            new ThemesCommand(),
            new BackupCommand(),
            new UpdateCommand(),
        ]);
        
        $this->setDefaultCommand('main-menu', true);
    }
}
