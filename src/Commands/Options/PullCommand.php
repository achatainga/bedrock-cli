<?php

namespace Roots\BedrockCli\Commands\Options;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class PullCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('options:pull')
             ->setAliases(['options:export', 'options:exportar'])
             ->setDescription('Exportar opciones de WordPress a archivos JSON')
             ->addArgument('prefix', InputArgument::OPTIONAL, 'Prefijo de opciones a extraer')
             ->addOption('all', null, InputOption::VALUE_NONE, 'Extraer todas las opciones')
             ->addOption('exclude', null, InputOption::VALUE_REQUIRED, 'Patrones a excluir (separados por coma)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configDir = getcwd() . '/config/options';
        
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $prefix = $input->getArgument('prefix');
        $all = $input->getOption('all');
        $exclude = $input->getOption('exclude');

        $count = $this->pullOptionsBatch($prefix, $all, $exclude, $configDir, $output);

        $output->writeln('');
        $output->writeln("<info>✓ Exportadas {$count} opciones exitosamente</info>");
        
        return Command::SUCCESS;
    }

    protected function pullOptionsBatch(?string $prefix, bool $all, ?string $exclude, string $dir, OutputInterface $output): int
    {
        $excludePatterns = $exclude ? explode(',', $exclude) : [];
        $defaultExclusions = $all ? ['_transient', '_site_transient', 'cron', '_user_roles', 'can_compress_scripts'] : [];
        
        $php = $this->generatePullScript($prefix, $all, $excludePatterns, $defaultExclusions);
        
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $process->setTimeout(300);
        
        $this->runWithLoader($process, $output, 'Exportando opciones desde WordPress');
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al exportar opciones</error>');
            return 0;
        }

        $payload = json_decode($process->getOutput(), true);
        $results = isset($payload['results']) ? $payload['results'] : $payload;
        $truncated = $payload['truncated'] ?? false;
        
        if (empty($results)) {
            $output->writeln('<comment>No se encontraron opciones para exportar</comment>');
            return 0;
        }

        if (!empty($truncated)) {
            $output->writeln('<comment>⚠ ADVERTENCIA: Se alcanzó el límite de seguridad de 50.000 opciones. Se recomienda refinar mediante --prefix.</comment>');
        }

        $count = 0;
        foreach ($results as $data) {
            $filename = "{$dir}/{$data['key']}.json";
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $output->writeln("<info>✓ Exportada: {$data['key']}</info>");
            $count++;
        }

        return $count;
    }

    protected function generatePullScript(?string $prefix, bool $all, array $excludePatterns, array $defaultExclusions): string
    {
        $excludeJson = json_encode(array_merge($excludePatterns, $defaultExclusions));
        $prefixJson = json_encode($prefix);
        $allStr = $all ? 'true' : 'false';
        
        return <<<PHP
global \$wpdb;
\$exclude = {$excludeJson};
\$prefix = {$prefixJson};
\$all = {$allStr};

\$keys = [];
\$chunkSize = 1000;
\$maxOptions = 50000;
\$truncated = false;

if (\$all) {
    \$lastOption = '';
    while (count(\$keys) < \$maxOptions) {
        if (\$lastOption === '') {
            \$chunk = \$wpdb->get_col(\$wpdb->prepare("SELECT option_name FROM {\$wpdb->options} ORDER BY option_name ASC LIMIT %d", \$chunkSize));
        } else {
            \$chunk = \$wpdb->get_col(\$wpdb->prepare("SELECT option_name FROM {\$wpdb->options} WHERE option_name > %s ORDER BY option_name ASC LIMIT %d", \$lastOption, \$chunkSize));
        }
        if (empty(\$chunk)) {
            break;
        }
        foreach (\$chunk as \$k) {
            \$keys[] = \$k;
            if (count(\$keys) >= \$maxOptions) {
                \$truncated = true;
                break;
            }
        }
        \$lastOption = end(\$chunk);
        if (count(\$chunk) < \$chunkSize) {
            break;
        }
    }
} elseif (\$prefix) {
    \$lastOption = '';
    \$like = \$wpdb->esc_like(\$prefix) . '%';
    while (count(\$keys) < \$maxOptions) {
        if (\$lastOption === '') {
            \$chunk = \$wpdb->get_col(\$wpdb->prepare("SELECT option_name FROM {\$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC LIMIT %d", \$like, \$chunkSize));
        } else {
            \$chunk = \$wpdb->get_col(\$wpdb->prepare("SELECT option_name FROM {\$wpdb->options} WHERE option_name LIKE %s AND option_name > %s ORDER BY option_name ASC LIMIT %d", \$like, \$lastOption, \$chunkSize));
        }
        if (empty(\$chunk)) {
            break;
        }
        foreach (\$chunk as \$k) {
            \$keys[] = \$k;
            if (count(\$keys) >= \$maxOptions) {
                \$truncated = true;
                break;
            }
        }
        \$lastOption = end(\$chunk);
        if (count(\$chunk) < \$chunkSize) {
            break;
        }
    }
} else {
    \$keys = ['blogname', 'blogdescription', 'siteurl', 'home'];
}

\$results = [];
foreach (\$keys as \$key) {
    \$skip = false;
    foreach (\$exclude as \$pattern) {
        if (strpos(\$key, trim(\$pattern)) !== false) {
            \$skip = true;
            break;
        }
    }
    if (\$skip) continue;
    
    \$value = get_option(\$key);
    if (\$value !== false) {
        \$results[] = ['key' => \$key, 'value' => \$value, 'type' => gettype(\$value)];
    }
}

echo json_encode(['results' => \$results, 'truncated' => \$truncated, 'total_keys' => count(\$keys)]);
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
