<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ImportCoreCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('import:core')
            ->setDescription('Importar golden-image.sql y aplicar configuraciones JSON')
            ->addOption('snapshot', null, InputOption::VALUE_REQUIRED, 'Nombre del snapshot a importar (default: golden-image)')
            ->addOption('skip-config', null, InputOption::VALUE_NONE, 'Omitir aplicación de configs JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectRoot = getcwd();
        $snapshotName = $input->getOption('snapshot') ?: 'golden-image';
        $skipConfig = $input->getOption('skip-config');

        $snapshotFile = "{$projectRoot}/database/snapshots/{$snapshotName}.sql";
        
        if (!file_exists($snapshotFile)) {
            $output->writeln("<error>Snapshot no encontrado: {$snapshotFile}</error>");
            return Command::FAILURE;
        }

        $output->writeln("<info>Importando: {$snapshotName}.sql</info>");

        if ($this->importSnapshot($snapshotFile, $output) !== Command::SUCCESS) {
            return Command::FAILURE;
        }

        if (!$skipConfig) {
            $output->writeln('');
            $output->writeln('<info>Aplicando configuraciones JSON...</info>');
            
            if ($this->applyConfigs($projectRoot, $output) !== Command::SUCCESS) {
                $output->writeln('<comment>Advertencia: Algunas configuraciones no se aplicaron</comment>');
            }
        }

        $output->writeln('');
        $output->writeln('<info>✓ Importación completada</info>');
        
        return Command::SUCCESS;
    }

    private function importSnapshot(string $filepath, OutputInterface $output): int
    {
        $dbName = getenv('DB_NAME') ?: 'bedrock';
        $dbUser = getenv('DB_USER') ?: 'root';
        $dbPass = getenv('DB_PASSWORD') ?: 'mysql';
        $dbHost = getenv('DB_HOST') ?: 'mysql';

        $sql = file_get_contents($filepath);
        $cmd = sprintf(
            'docker-compose exec -T %s mysql -u%s -p%s %s',
            $dbHost,
            $dbUser,
            $dbPass,
            $dbName
        );

        $process = Process::fromShellCommandline($cmd);
        $process->setInput($sql);
        $process->setTimeout(300);

        $this->runWithLoader($process, $output, 'Importando base de datos');

        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al importar snapshot</error>');
            $output->writeln($process->getErrorOutput());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function applyConfigs(string $projectRoot, OutputInterface $output): int
    {
        $configDir = "{$projectRoot}/config/options";
        
        if (!is_dir($configDir)) {
            $output->writeln('<comment>No hay configuraciones en config/options/</comment>');
            return Command::SUCCESS;
        }

        $configs = glob("{$configDir}/*.json");
        
        if (empty($configs)) {
            $output->writeln('<comment>No se encontraron archivos JSON</comment>');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($configs as $configFile) {
            $data = json_decode(file_get_contents($configFile), true);
            
            if (!$data || !isset($data['key'])) {
                continue;
            }

            $key = $data['key'];
            $value = $data['value'];
            $valueJson = json_encode($value);

            $php = "update_option('{$key}', json_decode('{$valueJson}', true)); echo 'OK';";
            
            $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
            $process->setTimeout(30);
            $process->run();

            if ($process->isSuccessful() && trim($process->getOutput()) === 'OK') {
                $output->writeln("<info>✓ {$key}</info>");
                $count++;
            }
        }

        $output->writeln('');
        $output->writeln("<info>✓ Aplicadas {$count} configuraciones</info>");

        return Command::SUCCESS;
    }

    private function runWithLoader(Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;

        $process->start();

        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }

        $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
    }
}
