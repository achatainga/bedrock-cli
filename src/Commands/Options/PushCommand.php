<?php

namespace Roots\BedrockCli\Commands\Options;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class PushCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('options:push')
             ->setAliases(['options:import', 'options:importar'])
             ->setDescription('Importar opciones desde archivos JSON a WordPress')
             ->addArgument('key', InputArgument::OPTIONAL, 'Clave específica o prefijo')
             ->addOption('all', null, InputOption::VALUE_NONE, 'Inyectar todas las opciones')
             ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simular sin aplicar cambios');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configDir = getcwd() . '/config/options';
        
        if (!is_dir($configDir)) {
            $output->writeln("<error>Directorio no encontrado: {$configDir}</error>");
            return Command::FAILURE;
        }

        $files = $this->getFiles($input, $configDir);
        
        if (empty($files)) {
            $output->writeln('<comment>No se encontraron archivos de configuración</comment>');
            return Command::SUCCESS;
        }

        $dryRun = $input->getOption('dry-run');
        $count = $this->pushOptionsBatch($files, $dryRun, $output);

        $verb = $dryRun ? 'Se importarían' : 'Importadas';
        $output->writeln('');
        $output->writeln("<info>✓ {$verb} {$count} opciones exitosamente</info>");
        
        return Command::SUCCESS;
    }

    protected function getFiles(InputInterface $input, string $dir): array
    {
        $key = $input->getArgument('key');
        
        if ($key) {
            $exact = "{$dir}/{$key}.json";
            if (file_exists($exact)) {
                return [$exact];
            }
            return glob("{$dir}/{$key}*.json") ?: [];
        }
        
        if ($input->getOption('all')) {
            return glob("{$dir}/*.json") ?: [];
        }
        
        $core = ['blogname', 'blogdescription', 'siteurl', 'home'];
        return array_filter(
            array_map(fn($k) => "{$dir}/{$k}.json", $core),
            fn($f) => file_exists($f)
        );
    }

    protected function pushOptionsBatch(array $files, bool $dryRun, OutputInterface $output): int
    {
        $options = [];
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && isset($data['key'], $data['value'])) {
                $options[] = $data;
            } else {
                $output->writeln("<error>Archivo inválido: {$file}</error>");
            }
        }

        if (empty($options)) {
            return 0;
        }

        $php = $this->generatePushScript($options, $dryRun);
        
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $process->setTimeout(300);
        
        $message = $dryRun ? 'Simulando importación de opciones' : 'Importando opciones a WordPress';
        $this->runWithLoader($process, $output, $message);
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al importar opciones</error>');
            return 0;
        }

        $results = json_decode($process->getOutput(), true);
        
        $count = 0;
        foreach ($results as $result) {
            if ($result['status'] === 'unchanged') {
                $output->writeln("<comment>⊘ Sin cambios: {$result['key']}</comment>");
            } elseif ($result['status'] === 'dry-run') {
                $output->writeln("<info>→ Se importaría: {$result['key']}</info>");
                $count++;
            } elseif ($result['status'] === 'updated') {
                $output->writeln("<info>✓ Importada: {$result['key']}</info>");
                $count++;
            }
        }

        return $count;
    }

    protected function generatePushScript(array $options, bool $dryRun): string
    {
        $optionsJson = json_encode($options);
        $dryRunStr = $dryRun ? 'true' : 'false';
        
        return <<<PHP
\$options = json_decode('{$optionsJson}', true);
\$dryRun = {$dryRunStr};
\$results = [];

foreach (\$options as \$opt) {
    \$current = get_option(\$opt['key']);
    
    if (\$current === \$opt['value']) {
        \$results[] = ['key' => \$opt['key'], 'status' => 'unchanged'];
        continue;
    }
    
    if (\$dryRun) {
        \$results[] = ['key' => \$opt['key'], 'status' => 'dry-run'];
    } else {
        update_option(\$opt['key'], \$opt['value']);
        \$results[] = ['key' => \$opt['key'], 'status' => 'updated'];
    }
}

echo json_encode(\$results);
PHP;
    }

    protected function runWithLoader(Process $process, OutputInterface $output, string $message): void
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
