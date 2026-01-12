<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Traits\FileSystemTrait;

abstract class BaseListCommand extends Command
{
    use FileSystemTrait;

    abstract protected function getItemType(): string; // 'plugin' o 'theme'

    protected function configure(): void
    {
        $type = $this->getItemType();
        $this->setName("{$type}s:list")
             ->setDescription("Lista todos los " . ($type === 'plugin' ? 'plugins' : 'temas') . " instalados");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $this->getItemType();
        $projectRoot = $this->detectProjectRoot();
        $itemsDir = $projectRoot . "/web/app/{$type}s";

        if (!is_dir($itemsDir)) {
            $output->writeln("<error>Carpeta de " . ($type === 'plugin' ? 'plugins' : 'themes') . " no encontrada</error>");
            return Command::FAILURE;
        }

        $items = $this->scanDirectory($itemsDir);

        if (empty($items)) {
            $output->writeln('<comment>No hay ' . ($type === 'plugin' ? 'plugins' : 'temas') . ' instalados</comment>');
            return Command::SUCCESS;
        }

        foreach ($items as $item) {
            $output->writeln($item);
        }

        return Command::SUCCESS;
    }
}
