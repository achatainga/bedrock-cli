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
    protected function configure(): void
    {
        $this->setName('remove:plugin')
             ->setDescription('Remover plugin del proyecto')
             ->addArgument('slug', InputArgument::REQUIRED, 'Slug del plugin');
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
        $pluginManager = new PluginManager($management);

        if (!$pluginManager->exists($slug)) {
            $output->writeln("<error>Plugin '{$slug}' no está instalado</error>");
            return Command::FAILURE;
        }

        $dependencyManager = new DependencyManager($management);

        $output->writeln("<info>Removiendo plugin: {$slug}</info>");

        $pluginManager->remove($slug);

        $package = "wpackagist-plugin/{$slug}";
        $exitCode = $dependencyManager->update(
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
