<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class OptionsPullCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('options:pull')
             ->setDescription('Extraer opciones de WordPress a archivos JSON')
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

        $options = $this->getOptions($input, $output);
        
        if (empty($options)) {
            $output->writeln('<comment>No se encontraron opciones</comment>');
            return Command::SUCCESS;
        }

        $count = 0;
        foreach ($options as $optionName) {
            if ($this->shouldPull($optionName, $input)) {
                if ($this->pullOption($optionName, $configDir, $output)) {
                    $count++;
                }
            }
        }

        $output->writeln('');
        $output->writeln("<info>✓ Extraídas {$count} opciones exitosamente</info>");
        
        return Command::SUCCESS;
    }

    protected function getOptions(InputInterface $input, OutputInterface $output): array
    {
        $prefix = $input->getArgument('prefix');
        $all = $input->getOption('all');

        if ($all) {
            $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'option', 'list', '--format=json']);
            $process->run();
            
            if (!$process->isSuccessful()) {
                $output->writeln('<error>Error al listar opciones</error>');
                return [];
            }
            
            $result = json_decode($process->getOutput(), true);
            return array_column($result, 'option_name');
        }

        if ($prefix) {
            $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'option', 'list', '--search=' . $prefix . '*', '--format=json']);
            $process->run();
            
            if (!$process->isSuccessful()) {
                $output->writeln('<error>Error al buscar opciones</error>');
                return [];
            }
            
            $result = json_decode($process->getOutput(), true);
            return array_column($result, 'option_name');
        }

        return ['blogname', 'blogdescription', 'siteurl', 'home'];
    }

    protected function shouldPull(string $key, InputInterface $input): bool
    {
        $exclude = $input->getOption('exclude');
        
        if (!$exclude && $input->getOption('all')) {
            $defaultExclusions = ['_transient', '_site_transient', 'cron', '_user_roles', 'can_compress_scripts'];
            foreach ($defaultExclusions as $pattern) {
                if (strpos($key, $pattern) !== false) {
                    return false;
                }
            }
        }
        
        if ($exclude) {
            $patterns = explode(',', $exclude);
            foreach ($patterns as $pattern) {
                if (strpos($key, trim($pattern)) !== false) {
                    return false;
                }
            }
        }
        
        return true;
    }

    protected function pullOption(string $key, string $dir, OutputInterface $output): bool
    {
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'option', 'get', $key, '--format=json']);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln("<comment>⊘ Opción '{$key}' no encontrada</comment>");
            return false;
        }

        $value = json_decode($process->getOutput(), true);
        
        $data = [
            'key' => $key,
            'value' => $value,
            'type' => gettype($value),
        ];

        $filename = "{$dir}/{$key}.json";
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        $output->writeln("<info>✓ Extraída: {$key}</info>");
        return true;
    }
}
