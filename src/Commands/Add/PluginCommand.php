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
    protected function configure(): void
    {
        $this->setName('add:plugin')
             ->setDescription('Agregar plugin al proyecto')
             ->addArgument('slug', InputArgument::REQUIRED, 'Slug del plugin')
             ->addOption('version', null, InputOption::VALUE_OPTIONAL, 'Versión específica', null)
             ->addOption('activate', null, InputOption::VALUE_NONE, 'Activar después de instalar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $contextDetector = new ContextDetector();
        $management = new ManagementService($contextDetector);

        try {
            $management->requireBedrockProject();
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $slug = $input->getArgument('slug');
        $version = $input->getOption('version');
        $activate = $input->getOption('activate');

        $pluginManager = new PluginManager($management);
        $dependencyManager = new DependencyManager($management);

        $output->writeln("<info>Instalando plugin: {$slug}</info>");

        $pluginManager->add($slug, 'wpackagist-plugin', $version);

        $package = "wpackagist-plugin/{$slug}";
        $exitCode = $dependencyManager->require(
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
