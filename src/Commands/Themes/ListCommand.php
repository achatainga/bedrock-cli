<?php

namespace Roots\BedrockCli\Commands\Themes;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\UnzipService;

class ListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('themes:list')
             ->setDescription('Lista todos los temas instalados');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $themesDir = $projectRoot . '/web/app/themes';

        if (!is_dir($themesDir)) {
            $output->writeln("<error>Carpeta de themes no encontrada</error>");
            return Command::FAILURE;
        }

        $themes = array_filter(scandir($themesDir), function($item) use ($themesDir) {
            return $item !== '.' && $item !== '..' && is_dir($themesDir . '/' . $item);
        });

        if (empty($themes)) {
            $output->writeln('<comment>No hay temas instalados</comment>');
            return Command::SUCCESS;
        }

        foreach ($themes as $theme) {
            $output->writeln($theme);
        }

        return Command::SUCCESS;
    }
}
