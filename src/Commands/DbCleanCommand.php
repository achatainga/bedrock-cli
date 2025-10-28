<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DbCleanCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('db:clean')
            ->setDescription('Limpiar base de datos (multisite, prefix, opciones)')
            ->addOption('from-multisite', null, InputOption::VALUE_NONE, 'Eliminar tablas multisite')
            ->addOption('old-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix antiguo', 'hp2f_')
            ->addOption('new-prefix', null, InputOption::VALUE_REQUIRED, 'Prefix nuevo', 'wp_');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();
        $scriptPath = $projectRoot . '/scripts/clean-database.php';

        if (!file_exists($scriptPath)) {
            $output->writeln('<error>Script clean-database.php no encontrado</error>');
            $output->writeln('<comment>Asegúrate de estar en la raíz del proyecto</comment>');
            return Command::FAILURE;
        }

        $oldPrefix = $input->getOption('old-prefix');
        $newPrefix = $input->getOption('new-prefix');

        $output->writeln('<info>Ejecutando limpieza de base de datos...</info>');
        $output->writeln('');

        $process = new Process(['php', $scriptPath, $oldPrefix, $newPrefix]);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $output->writeln('');
            $output->writeln('<error>Error al limpiar base de datos</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>✓ Base de datos limpiada exitosamente</info>');
        return Command::SUCCESS;
    }
}
