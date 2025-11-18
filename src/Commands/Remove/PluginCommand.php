<?php

namespace Roots\BedrockCli\Commands\Remove;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\PluginManager;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PluginCommand extends Command
{
    private ManagementService $managementService;
    private PluginManager $pluginManager;
    private DependencyManager $dependencyManager;

    public function __construct(ManagementService $managementService, PluginManager $pluginManager, DependencyManager $dependencyManager)
    {
        parent::__construct();
        $this->managementService = $managementService;
        $this->pluginManager = $pluginManager;
        $this->dependencyManager = $dependencyManager;
    }

    protected function configure(): void
    {
        $this->setName('remove:plugin')
             ->setDescription('Remover plugin del proyecto')
             ->addArgument('slug', InputArgument::REQUIRED, 'Slug del plugin');
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

        if (!$this->pluginManager->exists($slug)) {
            $output->writeln("<error>Plugin '{$slug}' no está instalado</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Removiendo plugin: {$slug}</info>");

        $this->pluginManager->remove($slug);

        $package = "wpackagist-plugin/{$slug}";
        $exitCode = $this->dependencyManager->update(
            [$package],
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode !== 0) {
            $output->writeln("<error>Error al remover plugin</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>✓ Plugin removido correctamente</info>");

        return Command::SUCCESS;
    }
}
