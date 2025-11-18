<?php

namespace Roots\BedrockCli\Commands\Remove;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\ThemeManager;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ThemeCommand extends Command
{
    private ManagementService $managementService;
    private ThemeManager $themeManager;
    private DependencyManager $dependencyManager;

    public function __construct(ManagementService $managementService, ThemeManager $themeManager, DependencyManager $dependencyManager)
    {
        parent::__construct();
        $this->managementService = $managementService;
        $this->themeManager = $themeManager;
        $this->dependencyManager = $dependencyManager;
    }

    protected function configure(): void
    {
        $this->setName('remove:theme')
             ->setDescription('Remover theme del proyecto')
             ->addArgument('slug', InputArgument::REQUIRED, 'Slug del theme');
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

        if (!$this->themeManager->exists($slug)) {
            $output->writeln("<error>Theme '{$slug}' no está instalado</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Removiendo theme: {$slug}</info>");

        $this->themeManager->remove($slug);

        $package = "wpackagist-theme/{$slug}";
        $exitCode = $this->dependencyManager->update(
            [$package],
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode !== 0) {
            $output->writeln("<error>Error al remover theme</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>✓ Theme removido correctamente</info>");

        return Command::SUCCESS;
    }
}
