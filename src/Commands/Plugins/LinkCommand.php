<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Process\Process;

class LinkCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugins:link')
             ->setDescription('Vincula repositorios locales de plugins de forma agnóstica al sistema operativo')
             ->addArgument('source', InputArgument::OPTIONAL, 'Ruta al directorio de plugins fuente (ej: ../dt24)')
             ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Ruta de destino en Bedrock', 'web/app/plugins')
             ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simular sin crear enlaces');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $input->getArgument('source');
        $target = $input->getOption('target');
        $dryRun = $input->getOption('dry-run');

        // Resolver directorio fuente si no fue provisto
        if (!$source) {
            $candidates = [
                '../dt24',
                '../../dt24',
                'C:/code/dt24',
                '/mnt/c/code/dt24',
            ];

            foreach ($candidates as $candidate) {
                if (is_dir($candidate)) {
                    $source = $candidate;
                    break;
                }
            }

            if (!$source) {
                $helper = $this->getHelper('question');
                $question = new Question('Directorio de plugins no encontrado automáticamente. Ingrese la ruta: ');
                $source = $helper->ask($input, $output, $question);
            }
        }

        if (!is_dir($source)) {
            $output->writeln("<error>El directorio fuente '{$source}' no existe o no es accesible.</error>");
            return Command::FAILURE;
        }

        $sourceRealPath = realpath($source);
        $output->writeln("<info>Escaneando plugins en: {$sourceRealPath}</info>");

        if (!is_dir($target)) {
            @mkdir($target, 0755, true);
        }

        $targetRealPath = realpath($target);
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        $items = scandir($sourceRealPath);
        $linkedCount = 0;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) {
                continue;
            }

            $itemPath = $sourceRealPath . DIRECTORY_SEPARATOR . $item;
            if (!is_dir($itemPath)) {
                continue;
            }

            $linkDestination = $targetRealPath . DIRECTORY_SEPARATOR . $item;

            if (file_exists($linkDestination) || is_link($linkDestination)) {
                $output->writeln("<comment>Omitiendo {$item}: ya existe en destino.</comment>");
                continue;
            }

            $output->write("<comment>Vinculando {$item}...</comment> ");

            if ($dryRun) {
                $output->writeln('<info>[DRY-RUN]</info>');
                $linkedCount++;
                continue;
            }

            if ($isWindows) {
                // En Windows usamos NTFS Junction de forma segura con Symfony Process
                $process = new Process(['cmd', '/c', 'mklink', '/J', $linkDestination, $itemPath]);
                $process->setTimeout(30);
                $process->run();
                if ($process->isSuccessful()) {
                    $output->writeln('<info>✓ Junction creado</info>');
                    $linkedCount++;
                } else {
                    $output->writeln("<error>✗ Error creando Junction: " . trim($process->getErrorOutput() ?: $process->getOutput()) . "</error>");
                }
            } else {
                // En Linux / macOS usamos symlink estándar
                if (@symlink($itemPath, $linkDestination)) {
                    $output->writeln('<info>✓ Symlink creado</info>');
                    $linkedCount++;
                } else {
                    $output->writeln('<error>✗ Error creando Symlink</error>');
                }
            }
        }

        $output->writeln('');
        $output->writeln("<info>✓ Proceso finalizado: {$linkedCount} plugins vinculados exitosamente.</info>");

        return Command::SUCCESS;
    }
}
