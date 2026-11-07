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
            'Roots\BedrockCli\Commands\Docker\DockerCommand' => ['DockerService', 'StateService'],
            'Roots\BedrockCli\Commands\Setup\SetupCommand' => ['StateService', 'DockerService', 'WpCliService', 'ProjectValidationService'],
            'Roots\BedrockCli\Commands\System\MainMenuCommand' => ['StateService', 'PremiumRepoService', 'ProjectValidationService'],
            'Roots\BedrockCli\Commands\System\DiagnosticsCommand' => ['ErrorLoggerService', 'CliRunnerService'],
        ];
        
        $commandInstances = [];
        foreach ($commands as $commandClass => $dependencies) {
            $deps = array_map(fn($dep) => $this->container->get("Roots\\BedrockCli\\Services\\{$dep}"), $dependencies);
            $commandInstances[] = new $commandClass(...$deps);
        }
        
        return $commandInstances;
    }
}