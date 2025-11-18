<?php

namespace Roots\BedrockCli\Commands\Add;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\PluginManager;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginCommand extends Command
{
    private ContextDetector $contextDetector;
    private ManagementService $managementService;
    private PluginManager $pluginManager;
    private DependencyManager $dependencyManager;

    public function __construct(ContextDetector $contextDetector, ManagementService $managementService, PluginManager $pluginManager, DependencyManager $dependencyManager)
    {
        parent::__construct();
        $this->contextDetector = $contextDetector;
        $this->managementService = $managementService;
        $this->pluginManager = $pluginManager;
        $this->dependencyManager = $dependencyManager;
    }

    protected function configure(): void
    {
        $this->setName('add:plugin')
             ->setDescription('Agregar plugin al proyecto')
             ->addArgument('slug', InputArgument::REQUIRED, 'Slug del plugin')
             ->addOption('plugin-version', 'pv', InputOption::VALUE_OPTIONAL, 'Versión específica', null)
             ->addOption('activate', null, InputOption::VALUE_NONE, 'Activar después de instalar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->managementService->requireBedrockProject();
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $slug = $input->getArgument('slug');
        $version = $input->getOption('plugin-version');
        $activate = $input->getOption('activate');

        $output->writeln("<info>Instalando plugin: {$slug}</info>");

        $this->pluginManager->add($slug, 'wpackagist-plugin', $version);

        $package = "wpackagist-plugin/{$slug}";
        $exitCode = $this->dependencyManager->require(
            $package,
            $version,
            false,
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode !== 0) {
            $output->writeln("<error>Error al instalar plugin</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>✓ Plugin instalado correctamente</info>");

        if ($activate) {
            $output->writeln("<comment>Nota: Usa WP-CLI para activar: wp plugin activate {$slug}</comment>");
        }

        return Command::SUCCESS;
    }
}
