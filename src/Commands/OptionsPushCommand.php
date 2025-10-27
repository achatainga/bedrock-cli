<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class OptionsPushCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('options:push')
             ->setDescription('Inyectar opciones desde archivos JSON a WordPress')
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

        $count = 0;
        foreach ($files as $file) {
            if ($this->pushOption($file, $input, $output)) {
                $count++;
            }
        }

        $verb = $input->getOption('dry-run') ? 'Se inyectarían' : 'Inyectadas';
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

    protected function pushOption(string $file, InputInterface $input, OutputInterface $output): bool
    {
        $data = json_decode(file_get_contents($file), true);
        
        if (!$data || !isset($data['key'], $data['value'])) {
            $output->writeln("<error>Archivo inválido: {$file}</error>");
            return false;
        }

        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'option', 'get', $data['key'], '--format=json']);
        $process->run();
        
        $current = $process->isSuccessful() ? json_decode($process->getOutput(), true) : null;
        
        if ($current === $data['value']) {
            $output->writeln("<comment>⊘ Sin cambios: {$data['key']}</comment>");
            return false;
        }
        
        if ($input->getOption('dry-run')) {
            $output->writeln("<info>→ Se inyectaría: {$data['key']}</info>");
            return true;
        }

        $valueJson = json_encode($data['value']);
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'option', 'update', $data['key'], $valueJson, '--format=json']);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln("<error>✗ Error al actualizar: {$data['key']}</error>");
            return false;
        }

        $output->writeln("<info>✓ Inyectada: {$data['key']}</info>");
        return true;
    }
}
