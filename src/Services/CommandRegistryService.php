<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Roots\BedrockCli\Application;

class CommandRegistryService
{
    private ContainerBuilder $container;
    
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }
    
    public function registerCommands(): array
    {
        $commands = [
            'Roots\BedrockCli\Commands\Acorn\AcornCommand' => ['StateService'],
            'Roots\BedrockCli\Commands\Database\MenuCommand' => ['DockerService', 'WpCliService'],
            'Roots\BedrockCli\Commands\Database\CleanCommand' => [],
            'Roots\BedrockCli\Commands\Database\SnapshotCommand' => [],
            'Roots\BedrockCli\Commands\Database\MigrateCommand' => [],
            'Roots\BedrockCli\Commands\Docker\DockerCommand' => ['DockerService', 'StateService'],
            'Roots\BedrockCli\Commands\Docker\UpdateConfigCommand' => [],
            'Roots\BedrockCli\Commands\Options\MenuCommand' => [],
            'Roots\BedrockCli\Commands\Options\PullCommand' => [],
            'Roots\BedrockCli\Commands\Options\PushCommand' => [],
            'Roots\BedrockCli\Commands\Options\ListCommand' => [],
            'Roots\BedrockCli\Commands\Options\ManageCommand' => [],
            'Roots\BedrockCli\Commands\Plugins\MenuCommand' => ['DockerService', 'WpCliService', 'UnzipService', 'ZipService'],
            'Roots\BedrockCli\Commands\Plugins\ListCommand' => [],
            'Roots\BedrockCli\Commands\Plugins\ActivateCommand' => ['DockerService', 'WpCliService', 'StateService'],
            'Roots\BedrockCli\Commands\Plugins\ActivateMultipleCommand' => ['PluginActivationService'],
            'Roots\BedrockCli\Commands\Plugins\DeactivateCommand' => ['DockerService', 'WpCliService'],
            'Roots\BedrockCli\Commands\Plugins\CompressCommand' => ['UnzipService', 'ZipService'],
            'Roots\BedrockCli\Commands\Plugins\StatusCommand' => ['DockerService', 'WpCliService'],
            'Roots\BedrockCli\Commands\Plugins\OrderCommand' => [],
            'Roots\BedrockCli\Commands\Plugins\OrderMenuCommand' => [],
            'Roots\BedrockCli\Commands\Plugins\OrderBuilderCommand' => [],
            'Roots\BedrockCli\Commands\Themes\MenuCommand' => ['DockerService', 'WpCliService', 'UnzipService', 'ZipService'],
            'Roots\BedrockCli\Commands\Themes\ListCommand' => ['UnzipService'],
            'Roots\BedrockCli\Commands\Themes\ActivateCommand' => ['DockerService', 'WpCliService', 'StateService'],
            'Roots\BedrockCli\Commands\Themes\CompressCommand' => [],
            'Roots\BedrockCli\Commands\Themes\StatusCommand' => [],
            'Roots\BedrockCli\Commands\Setup\SetupCommand' => ['StateService', 'DockerService', 'WpCliService', 'ProjectValidationService'],
            'Roots\BedrockCli\Commands\Setup\NewCommand' => ['ProfileService', 'ComposerService', 'BlueprintService', 'AuthService', 'StateService', 'ProjectValidationService', 'WebServerService'],
            'Roots\BedrockCli\Commands\Setup\NewWizardCommand' => ['WebServerService'],
            'Roots\BedrockCli\Commands\Setup\InitCommand' => ['StateService'],
            'Roots\BedrockCli\Commands\System\InitMenuCommand' => [],
            'Roots\BedrockCli\Commands\System\SearchMenuCommand' => [],
            'Roots\BedrockCli\Commands\System\LanguageCommand' => ['WpCliService'],
            'Roots\BedrockCli\Commands\System\InfoCommand' => ['ProjectValidationService'],
            'Roots\BedrockCli\Commands\System\DoctorCommand' => ['ProjectValidationService'],
            'Roots\BedrockCli\Commands\System\BackupCommand' => [],
            'Roots\BedrockCli\Commands\System\ReinstallCommand' => ['SecurityService', 'DockerService', 'WpCliService'],
            'Roots\BedrockCli\Commands\System\SeedCommand' => ['DockerService', 'WpCliService'],
            'Roots\BedrockCli\Commands\System\ExportConfigCommand' => [],
            'Roots\BedrockCli\Commands\System\ImportCoreCommand' => [],
            'Roots\BedrockCli\Commands\System\MainMenuCommand' => ['StateService', 'PremiumRepoService', 'ProjectValidationService'],
            'Roots\BedrockCli\Commands\System\DiagnosticsCommand' => ['ErrorLoggerService', 'CliRunnerService'],
            'Roots\BedrockCli\Commands\Profile\MenuCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\CreateCommand' => ['ProfileService', 'PremiumCacheService', 'VcsValidator'],
            'Roots\BedrockCli\Commands\Profile\ListCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\ShowCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\DeleteCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\EditCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\ExportCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\ApplyCommand' => ['ProfileService', 'ComposerService', 'VcsValidator'],
            'Roots\BedrockCli\Commands\Profile\AddPluginCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\RemovePluginCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\SetThemeCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\AddRepoCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\EditWizardCommand' => ['ProfileService', 'VcsValidator'],
            'Roots\BedrockCli\Commands\Profile\ManagePluginsCommand' => ['ProfileService'],
            'Roots\BedrockCli\Commands\Profile\ValidateVcsCommand' => ['ProfileService', 'VcsValidator'],
            'Roots\BedrockCli\Commands\Plugin\SearchCommand' => ['WordPressApiService', 'ProfileService'],
            'Roots\BedrockCli\Commands\Plugin\InfoCommand' => ['WordPressApiService', 'ProfileService'],
            'Roots\BedrockCli\Commands\Theme\SearchCommand' => ['WordPressApiService', 'ProfileService'],
            'Roots\BedrockCli\Commands\Theme\InfoCommand' => ['WordPressApiService', 'ProfileService'],
            'Roots\BedrockCli\Commands\Manage\ManageCommand' => ['Management\ContextDetector'],
            'Roots\BedrockCli\Commands\Manage\PluginsManageCommand' => ['Management\ContextDetector', 'Management\ManagementService', 'Management\PluginManager', 'Management\DependencyManager', 'WordPressApiService'],
            'Roots\BedrockCli\Commands\Manage\ThemesManageCommand' => ['Management\ContextDetector', 'Management\ManagementService', 'Management\ThemeManager', 'Management\DependencyManager', 'WordPressApiService'],
            'Roots\BedrockCli\Commands\Manage\DependenciesManageCommand' => ['Management\ContextDetector', 'Management\ManagementService', 'Management\DependencyManager'],
            'Roots\BedrockCli\Commands\Add\PluginCommand' => ['Management\ContextDetector', 'Management\ManagementService', 'Management\PluginManager', 'Management\DependencyManager'],
            'Roots\BedrockCli\Commands\Add\ThemeCommand' => ['Management\ContextDetector', 'Management\ManagementService', 'Management\ThemeManager', 'Management\DependencyManager'],
            'Roots\BedrockCli\Commands\Add\DependencyCommand' => ['Management\ContextDetector', 'Management\ManagementService', 'Management\DependencyManager'],
            'Roots\BedrockCli\Commands\Remove\PluginCommand' => ['Management\ManagementService', 'Management\PluginManager', 'Management\DependencyManager'],
            'Roots\BedrockCli\Commands\Remove\ThemeCommand' => ['Management\ManagementService', 'Management\ThemeManager', 'Management\DependencyManager'],
            'Roots\BedrockCli\Commands\Remove\DependencyCommand' => ['Management\ContextDetector', 'Management\ManagementService', 'Management\DependencyManager'],
            'Roots\BedrockCli\Commands\Auth\MenuCommand' => [],
            'Roots\BedrockCli\Commands\Auth\AddCommand' => ['AuthService'],
            'Roots\BedrockCli\Commands\Auth\ListCommand' => ['AuthService'],
            'Roots\BedrockCli\Commands\Auth\RemoveCommand' => ['AuthService'],
            'Roots\BedrockCli\Commands\AI\AICommand' => ['AIContextBuilder'],
            'Roots\BedrockCli\Commands\Install\InstallMuPluginCommand' => [],
            'Roots\BedrockCli\Commands\Install\UpdateMuPluginCommand' => [],
            'Roots\BedrockCli\Commands\Install\UpdateConfigCommand' => [],
            'Roots\BedrockCli\Commands\Cache\ImportCommand' => ['PremiumCacheService'],
            'Roots\BedrockCli\Commands\Cache\UpdateVersionCommand' => ['PremiumCacheService'],
            'Roots\BedrockCli\Commands\UpdateCommand' => ['DockerService', 'WpCliService'],
        ];
        
        $commandInstances = [];
        foreach ($commands as $commandClass => $dependencies) {
            $deps = array_map(function($dep) {
                // Handle nested services
                $serviceName = strpos($dep, '\\') === false ? "Roots\\BedrockCli\\Services\\{$dep}" : "Roots\\BedrockCli\\Services\\{$dep}";
                return $this->container->get($serviceName);
            }, $dependencies);
            $commandInstances[] = new $commandClass(...$deps);
        }
        
        return $commandInstances;
    }
}