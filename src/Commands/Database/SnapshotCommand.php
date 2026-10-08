<?php

namespace Roots\BedrockCli\Commands\Database;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Process\Process;

class SnapshotCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('snapshot')
            ->setDescription('Gestionar snapshots de base de datos')
            ->addOption('create', null, InputOption::VALUE_NONE, 'Crear snapshot')
            ->addOption('restore', null, InputOption::VALUE_NONE, 'Restaurar snapshot')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nombre del snapshot');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();
        $snapshotsDir = $projectRoot . '/database/snapshots';

        if (!is_dir($snapshotsDir)) {
            mkdir($snapshotsDir, 0755, true);
        }

        if ($input->getOption('create')) {
            return $this->createSnapshot($input, $output, $snapshotsDir);
        }

        if ($input->getOption('restore')) {
            return $this->restoreSnapshot($input, $output, $snapshotsDir);
        }

        $output->writeln('<error>Usa --create o --restore</error>');
        return Command::FAILURE;
    }

    private function createSnapshot(InputInterface $input, OutputInterface $output, string $snapshotsDir): int
    {
        $name = $input->getOption('name');
        if (!$name) {
            $output->writeln('<error>Especifica --name="backup-name"</error>');
            return Command::FAILURE;
        }

        $timestamp = date('YmdHis');
        $filename = "{$name}_{$timestamp}.sql";
        $filepath = "{$snapshotsDir}/{$filename}";

        $env = $this->loadEnv();
        $dbName = $env['DB_NAME'] ?? 'wordpress';
        $dbUser = $env['DB_USER'] ?? 'root';
        $dbPass = $env['DB_PASSWORD'] ?? 'mysql';
        $dbHost = $env['DB_HOST'] ?? 'mysql';

        $output->writeln("<info>Creando snapshot: {$filename}</info>");

        $fileHandle = fopen($filepath, 'wb');
        if (!$fileHandle) {
            $output->writeln("<error>No se pudo abrir archivo para escritura: {$filepath}</error>");
            return Command::FAILURE;
        }

        try {
            $process = new Process([
                'docker-compose', 'exec', '-T', $dbHost,
                'mysqldump', "-u{$dbUser}", "-p{$dbPass}", $dbName
            ]);
            $process->setTimeout(600);

            $this->runWithLoader($process, $output, 'Exportando base de datos', $fileHandle);

            if (!$process->isSuccessful()) {
                $output->writeln('<error>Error al crear snapshot</error>');
                $output->writeln($process->getErrorOutput());
                return Command::FAILURE;
            }
        } finally {
            fclose($fileHandle);
        }

        $output->writeln("<info>✓ Snapshot creado: {$filename}</info>");
        return Command::SUCCESS;
    }

    private function restoreSnapshot(InputInterface $input, OutputInterface $output, string $snapshotsDir): int
    {
        $name = $input->getOption('name');
        
        $snapshots = glob("{$snapshotsDir}/*.sql");
        if (empty($snapshots)) {
            $output->writeln('<error>No hay snapshots disponibles</error>');
            return Command::FAILURE;
        }

        $snapshots = array_map('basename', $snapshots);

        if ($name) {
            $matches = array_filter($snapshots, fn($s) => strpos($s, $name) === 0);
            if (empty($matches)) {
                $output->writeln("<error>No se encontró snapshot con nombre: {$name}</error>");
                return Command::FAILURE;
            }
            $selected = reset($matches);
        } else {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion('Selecciona snapshot a restaurar:', $snapshots);
            $selected = $helper->ask($input, $output, $question);
        }

        $filepath = "{$snapshotsDir}/{$selected}";

        $env = $this->loadEnv();
        $dbName = $env['DB_NAME'] ?? 'wordpress';
        $dbUser = $env['DB_USER'] ?? 'root';
        $dbPass = $env['DB_PASSWORD'] ?? 'mysql';
        $dbHost = $env['DB_HOST'] ?? 'mysql';

        $output->writeln("<info>Restaurando snapshot: {$selected}</info>");

        $fileHandle = fopen($filepath, 'rb');
        if (!$fileHandle) {
            $output->writeln("<error>No se pudo abrir snapshot: {$filepath}</error>");
            return Command::FAILURE;
        }

        try {
            $process = new Process([
                'docker-compose', 'exec', '-T', $dbHost,
                'mysql', "-u{$dbUser}", "-p{$dbPass}", $dbName
            ]);
            $process->setInput($fileHandle);
            $process->setTimeout(600);

            $this->runWithLoader($process, $output, 'Importando base de datos');

            if (!$process->isSuccessful()) {
                $output->writeln('<error>Error al restaurar snapshot</error>');
                $output->writeln($process->getErrorOutput());
                return Command::FAILURE;
            }
        } finally {
            fclose($fileHandle);
        }

        $output->writeln("<info>✓ Snapshot restaurado: {$selected}</info>");
        return Command::SUCCESS;
    }

    private function loadEnv(): array
    {
        $envFile = getcwd() . '/.env';
        if (!file_exists($envFile)) {
            return [];
        }

        $env = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $env[trim($key)] = trim($value, "' \"");
            }
        }
        
        return $env;
    }

    private function runWithLoader(Process $process, OutputInterface $output, string $message, $outputStream = null): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;

        $process->start(function ($type, $buffer) use ($outputStream, $process) {
            if ($outputStream !== null && $type === Process::OUT) {
                fwrite($outputStream, $buffer);
                $process->clearOutput();
            }
        });

        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }

        $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
    }
}
