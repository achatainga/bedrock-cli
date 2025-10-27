<?php

namespace Roots\BedrockCli;

use Symfony\Component\Console\Application as BaseApplication;
use Roots\BedrockCli\Commands\DockerCommand;
use Roots\BedrockCli\Commands\DatabaseCommand;
use Roots\BedrockCli\Commands\InstallCommand;
use Roots\BedrockCli\Commands\PluginsCommand;
use Roots\BedrockCli\Commands\ThemesCommand;
use Roots\BedrockCli\Commands\BackupCommand;
use Roots\BedrockCli\Commands\ReinstallCommand;
use Roots\BedrockCli\Commands\DoctorCommand;
use Roots\BedrockCli\Commands\MainMenuCommand;
use Roots\BedrockCli\Commands\SetupCommand;
use Roots\BedrockCli\Commands\ThemesListCommand;
use Roots\BedrockCli\Commands\ThemesActivateCommand;
use Roots\BedrockCli\Commands\ThemesCompressCommand;
use Roots\BedrockCli\Commands\ThemesStatusCommand;
use Roots\BedrockCli\Commands\PluginsListCommand;
use Roots\BedrockCli\Commands\PluginsActivateCommand;
use Roots\BedrockCli\Commands\PluginsDeactivateCommand;
use Roots\BedrockCli\Commands\PluginsCompressCommand;
use Roots\BedrockCli\Commands\PluginsStatusCommand;
use Roots\BedrockCli\Commands\PluginsOrderCommand;
use Roots\BedrockCli\Commands\SeedCommand;
use Roots\BedrockCli\Commands\OptionsCommand;
use Roots\BedrockCli\Commands\OptionsPullCommand;
use Roots\BedrockCli\Commands\OptionsPushCommand;
use Roots\BedrockCli\Commands\OptionsListCommand;
use Roots\BedrockCli\Commands\OptionsManageCommand;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('bedrock', '1.0.0');

        $this->addCommands([
            new MainMenuCommand(),
            new BackupCommand(),
            new DatabaseCommand(),
            new DockerCommand(),
            new DoctorCommand(),
            new InstallCommand(),
            new PluginsCommand(),
            new ReinstallCommand(),
            new SetupCommand(),
            new ThemesCommand(),
            new PluginsActivateCommand(),
            new PluginsCompressCommand(),
            new PluginsDeactivateCommand(),
            new PluginsListCommand(),
            new PluginsStatusCommand(),
            new PluginsOrderCommand(),
            new ThemesActivateCommand(),
            new ThemesCompressCommand(),
            new ThemesListCommand(),
            new ThemesStatusCommand(),
            new SeedCommand(),
            new OptionsCommand(),
            new OptionsPullCommand(),
            new OptionsPushCommand(),
            new OptionsListCommand(),
            new OptionsManageCommand(),
        ]);
    }

    public function getHelp(): string
    {
        return parent::getHelp() . "\n\n<comment>Abrir menu interactivo</comment>\n\n  <info>menu</info>           Abre el menú interactivo";
    }
}
