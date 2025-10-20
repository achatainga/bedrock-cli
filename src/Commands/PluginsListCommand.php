<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\UnzipService;

class PluginsListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugins:list')
             ->setDescription('Lista todos los plugins instalados');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $pluginsDir = $projectRoot . '/web/app/plugins';

        if (!is_dir($pluginsDir)) {
            $output->writeln("<error>Carpeta de plugins no encontrada</error>");
            return Command::FAILURE;
        }

        $plugins = array_filter(scandir($pluginsDir), function($item) use ($pluginsDir) {
            return $item !== '.' && $item !== '..' && is_dir($pluginsDir . '/' . $item);
        });

        if (empty($plugins)) {
            $output->writeln('<comment>No hay plugins instalados</comment>');
            return Command::SUCCESS;
        }

        foreach ($plugins as $plugin) {
            $output->writeln($plugin);
        }

        return Command::SUCCESS;
    }
}
