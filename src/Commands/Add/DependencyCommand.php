<?php

namespace Roots\BedrockCli\Commands\Add;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DependencyCommand extends Command
{
    private ContextDetector $contextDetector;
    private ManagementService $management;
    private DependencyManager $dependencyManager;

    public function __construct(
        ContextDetector $contextDetector,
        ManagementService $management,
        DependencyManager $dependencyManager
    ) {
        $this->contextDetector = $contextDetector;
        $this->management = $management;
        $this->dependencyManager = $dependencyManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('add:dependency')
             ->setDescription('Agregar dependencia Composer al proyecto')
             ->addArgument('package', InputArgument::REQUIRED, 'Nombre del paquete (vendor/package)')
             ->addOption('package-version', 'dv', InputOption::VALUE_OPTIONAL, 'Versión específica', null)
             ->addOption('dev', null, InputOption::VALUE_NONE, 'Agregar como dependencia de desarrollo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->management->requireBedrockProject();
        } catch (\RuntimeException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return Command::FAILURE;
        }

        $package = $input->getArgument('package');
        $version = $input->getOption('package-version');
        $isDev = $input->getOption('dev');

        if (!str_contains($package, '/')) {
            $output->writeln("<error>Formato inválido. Usa: vendor/package</error>");
            return Command::FAILURE;
        }



        $type = $isDev ? 'desarrollo' : 'producción';
        $output->writeln("<info>Instalando dependencia de {$type}: {$package}</info>");

        $exitCode = $this->dependencyManager->require(
            $package,
            $version,
            $isDev,
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode !== 0) {
            $output->writeln("<error>Error al instalar dependencia</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>✓ Dependencia instalada correctamente</info>");

        return Command::SUCCESS;
    }
}
