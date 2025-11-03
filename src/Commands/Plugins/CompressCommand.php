<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\UnzipService;
use Roots\BedrockCli\Services\ZipService;

class CompressCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugins:compress')
             ->setDescription('Comprime un plugin a ZIP')
             ->addArgument('plugin', InputArgument::REQUIRED, 'Nombre del plugin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $plugin = $input->getArgument('plugin');
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $pluginPath = $projectRoot . '/web/app/plugins/' . $plugin;
        $zipPath = $projectRoot . '/plugins/' . $plugin . '.zip';

        if (!is_dir($pluginPath)) {
            $output->writeln("<error>Plugin no encontrado: {$plugin}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Comprimiendo {$plugin}...</info>");
        $zipService = new ZipService();
        
        if ($zipService->compress($pluginPath, $zipPath, $output)) {
            $output->writeln("<info>✓ Plugin comprimido en: {$zipPath}</info>");
            return Command::SUCCESS;
        }

        $output->writeln('<error>Error al comprimir plugin</error>');
        return Command::FAILURE;
    }
}
