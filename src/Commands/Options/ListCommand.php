<?php

namespace Roots\BedrockCli\Commands\Options;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('options:list')
             ->setDescription('Listar archivos JSON de opciones disponibles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configDir = getcwd() . '/config/options';
        
        if (!is_dir($configDir)) {
            $output->writeln("<comment>Directorio no encontrado: {$configDir}</comment>");
            return Command::SUCCESS;
        }

        $files = glob("{$configDir}/*.json");
        
        if (empty($files)) {
            $output->writeln('<comment>No hay archivos JSON de opciones</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Opción', 'Tamaño', 'Modificado']);

        foreach ($files as $file) {
            $name = basename($file, '.json');
            $size = $this->formatSize(filesize($file));
            $modified = date('Y-m-d H:i', filemtime($file));
            
            $table->addRow([$name, $size, $modified]);
        }

        $output->writeln('');
        $output->writeln('<info>Archivos JSON de opciones:</info>');
        $output->writeln('');
        $table->render();
        $output->writeln('');
        $output->writeln('<comment>Total: ' . count($files) . ' archivo(s)</comment>');
        
        return Command::SUCCESS;
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
