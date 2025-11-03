<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class ExportConfigCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('export:config')
            ->setDescription('Exportar configuración de WordPress a JSON')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Exportar todas las opciones');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configDir = getcwd() . '/config/options';
        
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $all = $input->getOption('all');
        $excludePatterns = ['_transient', '_site_transient', 'cron', '_user_roles', 'can_compress_scripts'];

        $php = $this->generateExportScript($all, $excludePatterns);
        
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $process->setTimeout(300);
        
        $this->runWithLoader($process, $output, 'Exportando configuración desde WordPress');
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al exportar configuración</error>');
            return Command::FAILURE;
        }

        $results = json_decode($process->getOutput(), true);
        
        if (empty($results)) {
            $output->writeln('<comment>No se encontraron opciones para exportar</comment>');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($results as $data) {
            $filename = "{$configDir}/{$data['key']}.json";
            file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $output->writeln("<info>✓ {$data['key']}</info>");
            $count++;
        }

        $output->writeln('');
        $output->writeln("<info>✓ Exportadas {$count} opciones a config/options/</info>");
        
        return Command::SUCCESS;
    }

    protected function generateExportScript(bool $all, array $excludePatterns): string
    {
        $excludeJson = json_encode($excludePatterns);
        
        return <<<PHP
global \$wpdb;
\$exclude = {$excludeJson};

if ({$all}) {
    \$keys = \$wpdb->get_col("SELECT option_name FROM {\$wpdb->options} ORDER BY option_name");
} else {
    \$keys = ['blogname', 'blogdescription', 'siteurl', 'home', 'admin_email', 'timezone_string', 'date_format', 'time_format'];
}

\$results = [];
foreach (\$keys as \$key) {
    \$skip = false;
    foreach (\$exclude as \$pattern) {
        if (strpos(\$key, \$pattern) !== false) {
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
