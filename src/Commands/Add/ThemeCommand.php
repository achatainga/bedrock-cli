<?php

namespace Roots\BedrockCli\Commands\Add;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\ThemeManager;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ThemeCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('add:theme')
             ->setDescription('Agregar theme al proyecto')
             ->addArgument('slug', InputArgument::REQUIRED, 'Slug del theme')
             ->addOption('theme-version', 'v', InputOption::VALUE_OPTIONAL, 'Versión específica', null)
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
        $version = $input->getOption('theme-version');
        $activate = $input->getOption('activate');

        $themeManager = new ThemeManager($management);
        $dependencyManager = new DependencyManager($management);

        $output->writeln("<info>Instalando theme: {$slug}</info>");

        $themeManager->add($slug, 'wpackagist-theme', $version);

        $package = "wpackagist-theme/{$slug}";
        $exitCode = $dependencyManager->require(
            $package,
            $version,
            false,
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode !== 0) {
            $output->writeln("<error>Error al instalar theme</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>✓ Theme instalado correctamente</info>");

        if ($activate) {
            $output->writeln("<comment>Nota: Usa WP-CLI para activar: wp theme activate {$slug}</comment>");
        }

        return Command::SUCCESS;
    }
}
