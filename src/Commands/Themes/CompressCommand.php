<?php

namespace Roots\BedrockCli\Commands\Themes;

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
        $this->setName('themes:compress')
             ->setDescription('Comprime un tema a ZIP')
             ->addArgument('theme', InputArgument::REQUIRED, 'Nombre del tema');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $theme = $input->getArgument('theme');
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $themePath = $projectRoot . '/web/app/themes/' . $theme;
        $zipPath = $projectRoot . '/themes/' . $theme . '.zip';

        if (!is_dir($themePath)) {
            $output->writeln("<error>Tema no encontrado: {$theme}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Comprimiendo {$theme}...</info>");
        $zipService = new ZipService();
        
        if ($zipService->compress($themePath, $zipPath, $output)) {
            $output->writeln("<info>✓ Tema comprimido en: {$zipPath}</info>");
            return Command::SUCCESS;
        }

        $output->writeln('<error>Error al comprimir tema</error>');
        return Command::FAILURE;
    }
}
