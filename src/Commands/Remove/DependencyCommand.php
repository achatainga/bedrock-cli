<?php

namespace Roots\BedrockCli\Commands\Remove;

use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Services\Management\ManagementService;
use Roots\BedrockCli\Services\Management\DependencyManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DependencyCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('remove:dependency')
             ->setDescription('Remover dependencia Composer del proyecto')
             ->addArgument('package', InputArgument::REQUIRED, 'Nombre del paquete (vendor/package)');
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

        $package = $input->getArgument('package');

        if (!str_contains($package, '/')) {
            $output->writeln("<error>Formato inválido. Usa: vendor/package</error>");
            return Command::FAILURE;
        }

        $dependencyManager = new DependencyManager($management);

        if (!$dependencyManager->exists($package)) {
            $output->writeln("<error>Dependencia '{$package}' no está instalada</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Removiendo dependencia: {$package}</info>");

        $dependencyManager->remove($package);

        $exitCode = $dependencyManager->update(
            [$package],
            function($buffer) use ($output) {
                $output->write($buffer);
            }
        );

        if ($exitCode !== 0) {
            $output->writeln("<error>Error al remover dependencia</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>✓ Dependencia removida correctamente</info>");

        return Command::SUCCESS;
    }
}
