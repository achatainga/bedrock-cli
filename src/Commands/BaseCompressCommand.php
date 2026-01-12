<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\ZipService;
use Roots\BedrockCli\Traits\FileSystemTrait;

abstract class BaseCompressCommand extends Command
{
    use FileSystemTrait;

    protected ZipService $zipService;

    public function __construct(ZipService $zipService)
    {
        parent::__construct();
        $this->zipService = $zipService;
    }

    abstract protected function getItemType(): string; // 'plugin' o 'theme'

    protected function configure(): void
    {
        $type = $this->getItemType();
        $this->setName("{$type}s:compress")
             ->setDescription("Comprime un " . ($type === 'plugin' ? 'plugin' : 'tema') . " a ZIP")
             ->addArgument($type, InputArgument::REQUIRED, "Nombre del " . ($type === 'plugin' ? 'plugin' : 'tema'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $this->getItemType();
        $item = $input->getArgument($type);
        $projectRoot = $this->detectProjectRoot();
        
        $itemPath = $projectRoot . "/web/app/{$type}s/" . $item;
        $zipPath = $projectRoot . "/{$type}s/" . $item . '.zip';

        if (!is_dir($itemPath)) {
            $output->writeln("<error>" . ucfirst($type) . " no encontrado: {$item}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Comprimiendo {$item}...</info>");
        
        if ($this->zipService->compress($itemPath, $zipPath, $output)) {
            $output->writeln("<info>✓ " . ucfirst($type) . " comprimido en: {$zipPath}</info>");
            return Command::SUCCESS;
        }

        $output->writeln("<error>Error al comprimir {$type}</error>");
        return Command::FAILURE;
    }
}
