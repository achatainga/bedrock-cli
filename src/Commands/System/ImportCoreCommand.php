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
                $output->writeln('<error>Error al importar snapshot</error>');
                $output->writeln($process->getErrorOutput());
                return Command::FAILURE;
            }

            return Command::SUCCESS;
        } finally {
            fclose($fileHandle);
        }
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

        // Leer y consolidar todas las configuraciones
        $items = [];
        foreach ($configs as $configFile) {
            $data = json_decode(file_get_contents($configFile), true);
            if ($data && isset($data['key'])) {
                $items[] = [
                    'key' => $data['key'],
                    'value' => $data['value'] ?? null,
                ];
            }
        }

        if (empty($items)) {
            $output->writeln('<comment>No se encontraron opciones válidas para importar</comment>');
            return Command::SUCCESS;
        }

        $totalItems = count($items);
        $output->writeln("<comment>Importando {$totalItems} opciones en lote...</comment>");

        // Estrategia 1: Manifiesto consolidado en config/options/.import_manifest.json (1 solo subproceso)
        $manifestPath = "{$configDir}/.import_manifest.json";
        $manifestWritten = @file_put_contents($manifestPath, json_encode($items, JSON_UNESCAPED_UNICODE));
        $success = false;
        $count = 0;

        if ($manifestWritten !== false) {
            $php = <<<'PHP'
$possiblePaths = [
    getcwd() . '/config/options/.import_manifest.json',
    dirname(ABSPATH) . '/config/options/.import_manifest.json',
    '/var/www/html/config/options/.import_manifest.json',
];
$manifest = null;
foreach ($possiblePaths as $p) {
    if (file_exists($p)) {
        $manifest = $p;
        break;
    }
}
if (!$manifest) {
    echo "ERROR:NOT_FOUND";
    exit(1);
}
$data = json_decode(file_get_contents($manifest), true);
if (!is_array($data)) {
    echo "ERROR:INVALID_JSON";
    exit(1);
}
$applied = 0;
foreach ($data as $item) {
    if (isset($item['key'])) {
        update_option($item['key'], $item['value']);
        $applied++;
    }
}
echo "OK:" . $applied;
PHP;
            try {
                $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
                $process->setTimeout(300);
                $process->run();

                if ($process->isSuccessful() && str_starts_with(trim($process->getOutput()), 'OK:')) {
                    $parts = explode(':', trim($process->getOutput()));
                    $count = isset($parts[1]) ? intval($parts[1]) : count($items);
                    $success = true;
                }
            } finally {
                if (file_exists($manifestPath)) {
                    @unlink($manifestPath);
                }
            }
        }

        // Estrategia 2 (Fallback si el contenedor no mapeó el manifiesto): Chunking en bloques de 100
        if (!$success) {
            $chunks = array_chunk($items, 100);
            foreach ($chunks as $chunk) {
                $payloadB64 = base64_encode(json_encode($chunk, JSON_UNESCAPED_UNICODE));
                $chunkPhp = "foreach (json_decode(base64_decode('{$payloadB64}'), true) as \$item) { update_option(\$item['key'], \$item['value']); } echo 'OK';";
                $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $chunkPhp]);
                $process->setTimeout(60);
                $process->run();

                if ($process->isSuccessful() && trim($process->getOutput()) === 'OK') {
                    $count += count($chunk);
                }
            }
        }

        $output->writeln('');
        $output->writeln("<info>✓ Aplicadas {$count}/{$totalItems} configuraciones</info>");

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
