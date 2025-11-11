<?php

namespace Roots\BedrockCli;

use Symfony\Component\Console\Application as BaseApplication;
use Roots\BedrockCli\Commands\Acorn\AcornCommand;
use Roots\BedrockCli\Commands\Database\MenuCommand as DatabaseMenuCommand;
use Roots\BedrockCli\Commands\Database\CleanCommand as DatabaseCleanCommand;
use Roots\BedrockCli\Commands\Database\SnapshotCommand as DatabaseSnapshotCommand;
use Roots\BedrockCli\Commands\Database\MigrateCommand as DatabaseMigrateCommand;
use Roots\BedrockCli\Commands\Docker\DockerCommand;
use Roots\BedrockCli\Commands\Options\MenuCommand as OptionsMenuCommand;
use Roots\BedrockCli\Commands\Options\PullCommand as OptionsPullCommand;
use Roots\BedrockCli\Commands\Options\PushCommand as OptionsPushCommand;
use Roots\BedrockCli\Commands\Options\ListCommand as OptionsListCommand;
use Roots\BedrockCli\Commands\Options\ManageCommand as OptionsManageCommand;
use Roots\BedrockCli\Commands\Plugins\MenuCommand as PluginsMenuCommand;
use Roots\BedrockCli\Commands\Plugins\ListCommand as PluginsListCommand;
use Roots\BedrockCli\Commands\Plugins\ActivateCommand as PluginsActivateCommand;
use Roots\BedrockCli\Commands\Plugins\DeactivateCommand as PluginsDeactivateCommand;
use Roots\BedrockCli\Commands\Plugins\CompressCommand as PluginsCompressCommand;
use Roots\BedrockCli\Commands\Plugins\StatusCommand as PluginsStatusCommand;
use Roots\BedrockCli\Commands\Plugins\OrderCommand as PluginsOrderCommand;
use Roots\BedrockCli\Commands\Plugins\OrderMenuCommand as PluginsOrderMenuCommand;
use Roots\BedrockCli\Commands\Plugins\OrderBuilderCommand as PluginsOrderBuilderCommand;
use Roots\BedrockCli\Commands\Themes\MenuCommand as ThemesMenuCommand;
use Roots\BedrockCli\Commands\Themes\ListCommand as ThemesListCommand;
use Roots\BedrockCli\Commands\Themes\ActivateCommand as ThemesActivateCommand;
use Roots\BedrockCli\Commands\Themes\CompressCommand as ThemesCompressCommand;
use Roots\BedrockCli\Commands\Themes\StatusCommand as ThemesStatusCommand;
use Roots\BedrockCli\Commands\Setup\SetupCommand;
use Roots\BedrockCli\Commands\Setup\NewCommand;
use Roots\BedrockCli\Commands\Setup\NewWizardCommand;
use Roots\BedrockCli\Commands\Setup\InitCommand;
use Roots\BedrockCli\Commands\System\MainMenuCommand;
use Roots\BedrockCli\Commands\System\InitMenuCommand;
use Roots\BedrockCli\Commands\System\SearchMenuCommand;
use Roots\BedrockCli\Commands\System\InfoCommand;
use Roots\BedrockCli\Commands\System\DoctorCommand;
use Roots\BedrockCli\Commands\System\BackupCommand;
use Roots\BedrockCli\Commands\System\ReinstallCommand;
use Roots\BedrockCli\Commands\System\SeedCommand;
use Roots\BedrockCli\Commands\System\ExportConfigCommand;
use Roots\BedrockCli\Commands\System\ImportCoreCommand;
use Roots\BedrockCli\Commands\Profile\MenuCommand as ProfileMenuCommand;
use Roots\BedrockCli\Commands\Profile\CreateCommand as ProfileCreateCommand;
use Roots\BedrockCli\Commands\Profile\ListCommand as ProfileListCommand;
use Roots\BedrockCli\Commands\Profile\ShowCommand as ProfileShowCommand;
use Roots\BedrockCli\Commands\Profile\DeleteCommand as ProfileDeleteCommand;
use Roots\BedrockCli\Commands\Profile\EditCommand as ProfileEditCommand;
use Roots\BedrockCli\Commands\Profile\ExportCommand as ProfileExportCommand;
use Roots\BedrockCli\Commands\Profile\ApplyCommand as ProfileApplyCommand;
use Roots\BedrockCli\Commands\Profile\AddPluginCommand as ProfileAddPluginCommand;
use Roots\BedrockCli\Commands\Profile\RemovePluginCommand as ProfileRemovePluginCommand;
use Roots\BedrockCli\Commands\Profile\SetThemeCommand as ProfileSetThemeCommand;
use Roots\BedrockCli\Commands\Profile\AddRepoCommand as ProfileAddRepoCommand;
use Roots\BedrockCli\Commands\Profile\EditWizardCommand as ProfileEditWizardCommand;
use Roots\BedrockCli\Commands\Profile\ManagePluginsCommand as ProfileManagePluginsCommand;
use Roots\BedrockCli\Commands\Profile\ValidateVcsCommand as ProfileValidateVcsCommand;
use Roots\BedrockCli\Commands\Plugin\SearchCommand as PluginSearchCommand;
use Roots\BedrockCli\Commands\Plugin\InfoCommand as PluginInfoCommand;
use Roots\BedrockCli\Commands\Theme\SearchCommand as ThemeSearchCommand;
use Roots\BedrockCli\Commands\Theme\InfoCommand as ThemeInfoCommand;
use Roots\BedrockCli\Commands\Manage\ManageCommand;
use Roots\BedrockCli\Commands\Manage\PluginsManageCommand;
use Roots\BedrockCli\Commands\Manage\ThemesManageCommand;
use Roots\BedrockCli\Commands\Manage\DependenciesManageCommand;
use Roots\BedrockCli\Commands\Add\PluginCommand as AddPluginCommand;
use Roots\BedrockCli\Commands\Add\ThemeCommand as AddThemeCommand;
use Roots\BedrockCli\Commands\Add\DependencyCommand as AddDependencyCommand;
use Roots\BedrockCli\Commands\Remove\PluginCommand as RemovePluginCommand;
use Roots\BedrockCli\Commands\Remove\ThemeCommand as RemoveThemeCommand;
use Roots\BedrockCli\Commands\Remove\DependencyCommand as RemoveDependencyCommand;
use Roots\BedrockCli\Commands\Auth\MenuCommand as AuthMenuCommand;
use Roots\BedrockCli\Commands\Auth\AddCommand as AuthAddCommand;
use Roots\BedrockCli\Commands\Auth\ListCommand as AuthListCommand;
use Roots\BedrockCli\Commands\Auth\RemoveCommand as AuthRemoveCommand;
use Roots\BedrockCli\Commands\AI\AICommand;
use Roots\BedrockCli\Commands\AI\ConfigCommand as AIConfigCommand;
use Roots\BedrockCli\Commands\AI\AskCommand as AIAskCommand;
use Roots\BedrockCli\Commands\AI\ChatCommand as AIChatCommand;
use Roots\BedrockCli\Commands\AI\DiagnoseCommand as AIDiagnoseCommand;
use Roots\BedrockCli\Commands\AI\SuggestCommand as AISuggestCommand;
use Roots\BedrockCli\Commands\AI\ModelSelectorCommand as AIModelSelectorCommand;
use Roots\BedrockCli\Commands\Install\InstallMuPluginCommand;
use Roots\BedrockCli\Commands\Install\UpdateMuPluginCommand;
use Roots\BedrockCli\Commands\Cache\ImportCommand as CacheImportCommand;
use Roots\BedrockCli\Commands\Cache\UpdateVersionCommand as CacheUpdateVersionCommand;

class Application extends BaseApplication
{
    public function __construct()
    {
        parent::__construct('bedrock', '2.0.0');

        $this->addCommands([
            new AcornCommand(),
            new DatabaseMenuCommand(),
            new DatabaseCleanCommand(),
            new DatabaseSnapshotCommand(),
            new DatabaseMigrateCommand(),
            new DockerCommand(),
            new OptionsMenuCommand(),
            new OptionsPullCommand(),
            new OptionsPushCommand(),
            new OptionsListCommand(),
            new OptionsManageCommand(),
            new PluginsMenuCommand(),
            new PluginsListCommand(),
            new PluginsActivateCommand(),
            new PluginsDeactivateCommand(),
            new PluginsCompressCommand(),
            new PluginsStatusCommand(),
            new PluginsOrderCommand(),
            new PluginsOrderMenuCommand(),
            new PluginsOrderBuilderCommand(),
            new ThemesMenuCommand(),
            new ThemesListCommand(),
            new ThemesActivateCommand(),
            new ThemesCompressCommand(),
            new ThemesStatusCommand(),
            new SetupCommand(),
            new NewCommand(),
            new NewWizardCommand(),
            new InitCommand(),
            new MainMenuCommand(),
            new InitMenuCommand(),
            new SearchMenuCommand(),
            new InfoCommand(),
            new DoctorCommand(),
            new BackupCommand(),
            new ReinstallCommand(),
            new SeedCommand(),
            new ExportConfigCommand(),
            new ImportCoreCommand(),
            new ProfileMenuCommand(),
            new ProfileCreateCommand(),
            new ProfileListCommand(),
            new ProfileShowCommand(),
            new ProfileDeleteCommand(),
            new ProfileEditCommand(),
            new ProfileExportCommand(),
            new ProfileApplyCommand(),
            new ProfileAddPluginCommand(),
            new ProfileRemovePluginCommand(),
            new ProfileSetThemeCommand(),
            new ProfileAddRepoCommand(),
            new ProfileEditWizardCommand(),
            new ProfileManagePluginsCommand(),
            new ProfileValidateVcsCommand(),
            new PluginSearchCommand(),
            new PluginInfoCommand(),
            new ThemeSearchCommand(),
            new ThemeInfoCommand(),
            new ManageCommand(),
            new PluginsManageCommand(),
            new ThemesManageCommand(),
            new DependenciesManageCommand(),
            new AddPluginCommand(),
            new AddThemeCommand(),
            new AddDependencyCommand(),
            new RemovePluginCommand(),
            new RemoveThemeCommand(),
            new RemoveDependencyCommand(),
            new AuthMenuCommand(),
            new AuthAddCommand(),
            new AuthListCommand(),
            new AuthRemoveCommand(),
            new AICommand(),
            new AIConfigCommand(),
            new AIAskCommand(),
            new AIChatCommand(),
            new AIDiagnoseCommand(),
            new AISuggestCommand(),
            new AIModelSelectorCommand(),
            new InstallMuPluginCommand(),
            new UpdateMuPluginCommand(),
            new CacheImportCommand(),
            new CacheUpdateVersionCommand(),
        ]);
    }

    public function getHelp(): string
    {
        return parent::getHelp() . "\n\n<comment>Abrir menu interactivo</comment>\n\n  <info>menu</info>           Abre el menú interactivo";
    }
}
