<?php

namespace Roots\BedrockCli;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Roots\BedrockCli\Commands\Acorn\AcornCommand;
use Roots\BedrockCli\Commands\Database\MenuCommand as DatabaseMenuCommand;
use Roots\BedrockCli\Commands\Database\CleanCommand as DatabaseCleanCommand;
use Roots\BedrockCli\Commands\Database\SnapshotCommand as DatabaseSnapshotCommand;
use Roots\BedrockCli\Commands\Database\MigrateCommand as DatabaseMigrateCommand;
use Roots\BedrockCli\Commands\Docker\DockerCommand;
use Roots\BedrockCli\Commands\Docker\UpdateConfigCommand;
use Roots\BedrockCli\Commands\Options\MenuCommand as OptionsMenuCommand;
use Roots\BedrockCli\Commands\Options\PullCommand as OptionsPullCommand;
use Roots\BedrockCli\Commands\Options\PushCommand as OptionsPushCommand;
use Roots\BedrockCli\Commands\Options\ListCommand as OptionsListCommand;
use Roots\BedrockCli\Commands\Options\ManageCommand as OptionsManageCommand;
use Roots\BedrockCli\Commands\Plugins\MenuCommand as PluginsMenuCommand;
use Roots\BedrockCli\Commands\Plugins\ListCommand as PluginsListCommand;
use Roots\BedrockCli\Commands\Plugins\ActivateCommand as PluginsActivateCommand;
use Roots\BedrockCli\Commands\Plugins\ActivateMultipleCommand as PluginsActivateMultipleCommand;
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
use Roots\BedrockCli\Commands\System\LanguageCommand;
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
use Roots\BedrockCli\Commands\Install\InstallMuPluginCommand;
use Roots\BedrockCli\Commands\Install\UpdateMuPluginCommand;
use Roots\BedrockCli\Commands\Install\UpdateConfigCommand as InstallUpdateConfigCommand;
use Roots\BedrockCli\Commands\Cache\ImportCommand as CacheImportCommand;
use Roots\BedrockCli\Commands\Cache\UpdateVersionCommand as CacheUpdateVersionCommand;
use Roots\BedrockCli\Commands\UpdateCommand;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ExecutableFinder;

class Application extends BaseApplication
{
    private ContainerBuilder $container;

    public function __construct()
    {
        $this->container = new ContainerBuilder();
        $this->configureServices();
        
        parent::__construct('bedrock', $this->getVersion());

        // Usar CommandRegistryService para todos los comandos
        $commandRegistry = $this->container->get('Roots\BedrockCli\Services\CommandRegistryService');
        $registryCommands = $commandRegistry->registerCommands();
        
        $this->addCommands($registryCommands);
    }

    private function configureServices(): void
    {
        // Register core services
        $this->container->register('Roots\BedrockCli\Services\ProfileService', 'Roots\BedrockCli\Services\ProfileService');
        $this->container->register('Roots\BedrockCli\Services\ComposerService', 'Roots\BedrockCli\Services\ComposerService');
        $this->container->register('Roots\BedrockCli\Services\DockerService', 'Roots\BedrockCli\Services\DockerService');
        $this->container->register('Roots\BedrockCli\Services\WpCliService', 'Roots\BedrockCli\Services\WpCliService');
        $this->container->register('Roots\BedrockCli\Services\WordPressApiService', 'Roots\BedrockCli\Services\WordPressApiService');
        $this->container->register('Roots\BedrockCli\Services\PremiumRepoService', 'Roots\BedrockCli\Services\PremiumRepoService')
            ->addArgument(new Reference('Roots\BedrockCli\Services\AuthService'));
        $this->container->register('Roots\BedrockCli\Services\AuthService', 'Roots\BedrockCli\Services\AuthService');
        $this->container->register('Roots\BedrockCli\Services\BlueprintService', 'Roots\BedrockCli\Services\BlueprintService');
        $this->container->register('Roots\BedrockCli\Services\StateService', 'Roots\BedrockCli\Services\StateService');
        $this->container->register('Roots\BedrockCli\Services\SecurityService', 'Roots\BedrockCli\Services\SecurityService');
        $this->container->register('Roots\BedrockCli\Services\ProjectValidationService', 'Roots\BedrockCli\Services\ProjectValidationService')
            ->addArgument(new Reference('Roots\BedrockCli\Services\Management\ContextDetector'));
        $this->container->register('Roots\BedrockCli\Services\Management\ContextDetector', 'Roots\BedrockCli\Services\Management\ContextDetector')
            ->addArgument(new Reference('Roots\BedrockCli\Services\DockerService'));
        $this->container->register('Roots\BedrockCli\Services\Management\ManagementService', 'Roots\BedrockCli\Services\Management\ManagementService')
            ->addArgument(new Reference('Roots\BedrockCli\Services\Management\ContextDetector'));
        $this->container->register('Roots\BedrockCli\Services\Management\PluginManager', 'Roots\BedrockCli\Services\Management\PluginManager')
            ->addArgument(new Reference('Roots\BedrockCli\Services\Management\ManagementService'));
        $this->container->register('Roots\BedrockCli\Services\Management\ThemeManager', 'Roots\BedrockCli\Services\Management\ThemeManager')
            ->addArgument(new Reference('Roots\BedrockCli\Services\Management\ManagementService'));
        $this->container->register('Roots\BedrockCli\Services\Management\DependencyManager', 'Roots\BedrockCli\Services\Management\DependencyManager')
            ->addArgument(new Reference('Roots\BedrockCli\Services\Management\ManagementService'));
        $this->container->register('Roots\BedrockCli\Services\VcsValidator', 'Roots\BedrockCli\Services\VcsValidator');
        $this->container->register('Roots\BedrockCli\Services\PremiumCacheService', 'Roots\BedrockCli\Services\PremiumCacheService');
        $this->container->register('Roots\BedrockCli\Services\UnzipService', 'Roots\BedrockCli\Services\UnzipService');
        $this->container->register('Roots\BedrockCli\Services\ZipService', 'Roots\BedrockCli\Services\ZipService');
        $this->container->register('Roots\BedrockCli\Services\PluginActivationService', 'Roots\BedrockCli\Services\PluginActivationService')
            ->addArgument(new Reference('Roots\BedrockCli\Services\WpCliService'));
        $this->container->register('Roots\BedrockCli\Services\ProgressService', 'Roots\BedrockCli\Services\ProgressService');
        $this->container->register('Roots\BedrockCli\Services\ProjectDiagnosticService', 'Roots\BedrockCli\Services\ProjectDiagnosticService');
        $this->container->register('Roots\BedrockCli\Services\OrderValidator', 'Roots\BedrockCli\Services\OrderValidator');
        $this->container->register('Roots\BedrockCli\Services\AIContextBuilder', 'Roots\BedrockCli\Services\AIContextBuilder');
        $this->container->register('Roots\BedrockCli\Services\ErrorLoggerService', 'Roots\BedrockCli\Services\ErrorLoggerService');
        
        // Add WpCliService with DockerService dependency
        $this->container->register('Roots\BedrockCli\Services\WpCliService', 'Roots\BedrockCli\Services\WpCliService')
            ->addArgument(new Reference('Roots\BedrockCli\Services\DockerService'))
            ->addArgument(new Reference('Roots\BedrockCli\Services\Management\ContextDetector'));
        
        // Add StateService with ProjectValidationService dependency
        $this->container->register('Roots\BedrockCli\Services\StateService', 'Roots\BedrockCli\Services\StateService')
            ->addArgument(new Reference('Roots\BedrockCli\Services\ProjectValidationService'));
        
        // Add SecurityService
        $this->container->register('Roots\BedrockCli\Services\SecurityService', 'Roots\BedrockCli\Services\SecurityService');
        
        // Add ErrorLoggerService
        $this->container->register('Roots\BedrockCli\Services\ErrorLoggerService', 'Roots\BedrockCli\Services\ErrorLoggerService');
        
        // Add CliRunnerService with ErrorLoggerService dependency
        $this->container->register('Roots\BedrockCli\Services\CliRunnerService', 'Roots\BedrockCli\Services\CliRunnerService')
            ->addArgument(new Reference('Roots\BedrockCli\Services\ErrorLoggerService'));
        
        // Add WebServerService
        $this->container->register('Roots\BedrockCli\Services\WebServerService', 'Roots\BedrockCli\Services\WebServerService');
        
        // Add CommandRegistryService
        $this->container->register('Roots\BedrockCli\Services\CommandRegistryService', 'Roots\BedrockCli\Services\CommandRegistryService')
            ->addArgument($this->container);
    }

    public function getContainer(): ContainerBuilder
    {
        return $this->container;
    }

    public function getHelp(): string
    {
        return parent::getHelp() . "\n\n<comment>Abrir menu interactivo</comment>\n\n  <info>menu</info>           Abre el menú interactivo";
    }

    public function getVersion(): string
    {
        // 1. Composer InstalledVersions (instalación global)
        if (class_exists('\Composer\InstalledVersions')) {
            try {
                $version = \Composer\InstalledVersions::getVersion('achatainga/bedrock-cli');
                if ($version && $version !== 'dev-feature/unified-management-system') {
                    return $version;
                }
                $reference = \Composer\InstalledVersions::getReference('achatainga/bedrock-cli');
                if ($reference) {
                    return 'dev-' . substr($reference, 0, 7);
                }
            } catch (\Exception $e) {
                // Continuar con otros métodos
            }
        }
        
        // 2. Skip git commands - errores vienen del entorno, no del código
        // Los errores "system cannot find path" son del entorno PHP/Windows, no de bedrock-cli
        
        // 3. Composer.json version
        $composerPath = __DIR__ . '/../composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if (isset($composer['version'])) {
                return $composer['version'];
            }
        }
        
        return '2.0.0-unknown';
    }
}
