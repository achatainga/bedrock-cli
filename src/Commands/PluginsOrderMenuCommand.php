<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Cursor;

class PluginsOrderMenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugins:order:menu')
             ->setDescription('Menú interactivo para gestión de orden de plugins');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>   Secuencia de Carga de Plugins  </> <fg=cyan;options=bold>       ║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');
            $output->writeln('<comment>Controla el orden en que WordPress activa los plugins.</comment>');
            $output->writeln('<comment>Importante para resolver dependencias entre plugins.</comment>');
            $output->writeln('');

            $choices = [
                1 => '<fg=cyan>Ver Estado</>         - Listar plugins y su orden (solo lectura)',
                2 => '<fg=green>Guardar Orden</>      - Capturar secuencia actual de plugins activos',
                3 => '<fg=yellow>Aplicar Orden</>      - Activar plugins según configuración guardada',
                4 => '<fg=magenta>Configuraciones</>    - Ver/usar archivos JSON guardados',
                0 => '<fg=red>Volver</>             - (sin cambios)',
            ];

            $question = new ChoiceQuestion('<fg=yellow>Selecciona una opción:</>', $choices, 1);
            $question->setAutocompleterValues(null);
            $choice = $helper->ask($input, $output, $question);
            
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            $selectedIndex = array_search($choice, $choices);
            
            if ($selectedIndex === 0) {
                return Command::SUCCESS;
            }

            $output->writeln('');
            
            switch ($selectedIndex) {
                case 1:
                    $this->runCommand('plugins:order', ['action' => 'list'], $input, $output);
                    break;
                case 2:
                    $this->runCommand('plugins:order', ['action' => 'save'], $input, $output);
                    break;
                case 3:
                    $this->runCommand('plugins:order', ['action' => 'activate'], $input, $output);
                    break;
                case 4:
                    $this->manageJsonFiles($input, $output, $helper);
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    protected function manageJsonFiles(InputInterface $input, OutputInterface $output, $helper): void
    {
        $pluginsDir = getcwd() . '/config/plugins';
        
        // Solo buscar en config/plugins/
        $files = glob("{$pluginsDir}/*.json") ?: [];

        if (empty($files)) {
            $output->writeln('<comment>No hay archivos JSON de configuración</comment>');
            return;
        }

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>Configuraciones Guardadas:</>');
        $output->writeln('');
        $output->writeln('<comment>Estos archivos contienen diferentes órdenes de activación.</comment>');
        $output->writeln('<comment>Puedes ver su contenido antes de aplicarlos.</comment>');
        $output->writeln('');

        $choices = [];
        $fileMap = [];
        $index = 1;
        
        foreach ($files as $file) {
            $name = basename($file);
            $size = $this->formatSize(filesize($file));
            $modified = date('Y-m-d H:i', filemtime($file));
            $choices[$index] = "<fg=green>{$name}</> ({$size} - {$modified})";
            $fileMap[$index] = $file;
            $index++;
        }
        
        $choices[0] = '<fg=red>Volver</>';

        $question = new ChoiceQuestion('<fg=yellow>Selecciona un archivo:</>', $choices, 0);
        $question->setAutocompleterValues(null);
        $choice = $helper->ask($input, $output, $question);
        
        $cursor = new Cursor($output);
        $cursor->moveUp(1);
        $cursor->clearLine();
        
        $selectedIndex = array_search($choice, $choices);
        
        if ($selectedIndex === 0) {
            return;
        }

        $selectedFile = $fileMap[$selectedIndex];
        $this->manageFile($selectedFile, $input, $output, $helper);
    }

    protected function manageFile(string $file, InputInterface $input, OutputInterface $output, $helper): void
    {
        $filename = basename($file);
        
        $output->writeln('');
        $output->writeln("<info>📄 Archivo: {$filename}</info>");
        $output->writeln('');
        $output->writeln('<comment>Puedes revisar el contenido antes de aplicar cambios.</comment>');
        $output->writeln('');

        $choices = [
            1 => '<fg=cyan>Ver Contenido</>             - Mostrar plugins y orden (solo lectura)',
            2 => '<fg=yellow>Aplicar Ahora</>           - Activar plugins con este orden',
            3 => '<fg=green>Hacer Predeterminado</>     - Copiar a activation-order.json',
            0 => '<fg=red>Volver</>                     (sin cambios)',
        ];

        $question = new ChoiceQuestion('<fg=yellow>¿Qué deseas hacer?</>', $choices, 1);
        $question->setAutocompleterValues(null);
        $choice = $helper->ask($input, $output, $question);
        
        $cursor = new Cursor($output);
        $cursor->moveUp(1);
        $cursor->clearLine();
        
        $selectedIndex = array_search($choice, $choices);
        
        $output->writeln('');
        
        switch ($selectedIndex) {
            case 1:
                $this->showContent($file, $output);
                break;
            case 2:
                $this->activateFromFile($file, $output);
                break;
            case 3:
                $this->copyToDefault($file, $output);
                break;
        }
    }

    protected function showContent(string $file, OutputInterface $output): void
    {
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        $output->writeln('<fg=cyan>Contenido del archivo:</>');
        $output->writeln('');
        
        if (isset($data['activation_order'])) {
            $order = $data['activation_order'];
            asort($order);
            $count = count($order);
            $output->writeln("<info>Plugins en orden ({$count}):</info>");
            $output->writeln('');
            
            // Mostrar en 2 columnas
            $plugins = [];
            foreach ($order as $plugin => $position) {
                $plugins[] = sprintf("[%2d] %s", $position, $plugin);
            }
            
            $half = (int)ceil(count($plugins) / 2);
            $col1 = array_slice($plugins, 0, $half);
            $col2 = array_slice($plugins, $half);
            
            $maxLen = max(array_map('strlen', $plugins));
            
            for ($i = 0; $i < $half; $i++) {
                $left = isset($col1[$i]) ? sprintf("  %-{$maxLen}s", $col1[$i]) : str_repeat(' ', $maxLen + 2);
                $right = isset($col2[$i]) ? "  {$col2[$i]}" : '';
                $output->writeln("<fg=green>{$left}</>	<fg=green>{$right}</>");
            }
        }
        
        if (isset($data['dependencies'])) {
            $deps = $data['dependencies'];
            $output->writeln('');
            $output->writeln('<info>Dependencias:</info>');
            foreach ($deps as $plugin => $requires) {
                $output->writeln("  {$plugin} → " . implode(', ', $requires));
            }
        }
    }

    protected function activateFromFile(string $file, OutputInterface $output): void
    {
        $output->writeln("<info>🚀 Aplicando configuración: " . basename($file) . "</info>");
        $output->writeln('');
        $output->writeln('<comment>Los plugins se activarán en el orden especificado...</comment>');
        $output->writeln('');
        
        $this->runCommand('plugins:order', [
            'action' => 'activate',
            '--config' => $file
        ], null, $output);
    }

    protected function copyToDefault(string $file, OutputInterface $output): void
    {
        $defaultFile = getcwd() . '/config/plugins/activation-order.json';
        
        if (!is_dir(dirname($defaultFile))) {
            mkdir(dirname($defaultFile), 0755, true);
        }
        
        copy($file, $defaultFile);
        
        $output->writeln("<info>✓ Copiado a: {$defaultFile}</info>");
    }

    protected function runCommand(string $name, array $arguments, ?InputInterface $input, OutputInterface $output): void
    {
        $command = $this->getApplication()->find($name);
        
        $arrayInput = new \Symfony\Component\Console\Input\ArrayInput($arguments);
        $command->run($arrayInput, $output);
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
