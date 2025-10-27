<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Roots\BedrockCli\Services\UnzipService;

class PluginsOrderBuilderCommand extends Command
{
    private array $plugins = [];
    private array $activationOrder = [];
    private array $dependencies = [];

    protected function configure(): void
    {
        $this->setName('plugins:order:build')
             ->setDescription('Constructor interactivo de orden de activación');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $unzipService = new UnzipService();
        $projectRoot = $unzipService->detectProjectRoot();
        $pluginsDir = $projectRoot . '/web/app/plugins';

        if (!is_dir($pluginsDir)) {
            $output->writeln("<error>Carpeta de plugins no encontrada: {$pluginsDir}</error>");
            return Command::FAILURE;
        }

        $this->plugins = array_filter(scandir($pluginsDir), function($item) use ($pluginsDir) {
            return $item !== '.' && $item !== '..' && is_dir($pluginsDir . '/' . $item);
        });

        if (empty($this->plugins)) {
            $output->writeln('<comment>No hay plugins instalados</comment>');
            return Command::FAILURE;
        }

        $this->plugins = array_values($this->plugins);

        // Mostrar header y plugins disponibles
        $this->displayHeader($output);
        $this->displayPluginsInColumns($output);
        $this->displaySeparator($output);
        $this->displayCurrentOrder($output);
        $this->displaySeparator($output);
        $this->displayHelp($output);

        // Loop de comandos
        while (true) {
            $question = new Question("\n<fg=yellow>></> ");
            $command = trim($helper->ask($input, $output, $question));

            if (empty($command)) {
                continue;
            }

            $result = $this->processCommand($command, $output);

            if ($result === 'save') {
                break;
            } elseif ($result === 'cancel') {
                $output->writeln('<comment>Operación cancelada</comment>');
                return Command::SUCCESS;
            }
        }

        if (empty($this->activationOrder)) {
            $output->writeln('<comment>No se seleccionaron plugins</comment>');
            return Command::SUCCESS;
        }

        // Preguntar por dependencias
        $this->askDependencies($input, $output, $helper);

        // Recalcular orden basado en dependencias
        $finalOrder = $this->calculateOrderWithDependencies();

        // Guardar
        $this->saveActivationOrder($input, $output, $helper, $finalOrder);

        return Command::SUCCESS;
    }

    private function displayHeader(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Constructor de Orden de Activación de Plugins       </> <fg=cyan;options=bold>       ║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln('<fg=cyan>Plugins disponibles:</>');
        $output->writeln('');
    }

    private function displayPluginsInColumns(OutputInterface $output): void
    {
        $half = (int)ceil(count($this->plugins) / 2);
        $col1 = array_slice($this->plugins, 0, $half);
        $col2 = array_slice($this->plugins, $half);

        $maxLen = max(array_map('strlen', $this->plugins));

        for ($i = 0; $i < $half; $i++) {
            $num1 = $i + 1;
            $num2 = $i + $half + 1;
            
            // Columna izquierda
            if (isset($col1[$i])) {
                $plugin1 = $col1[$i];
                $isSelected1 = isset($this->activationOrder[$plugin1]);
                $left = $this->formatPlugin($num1, $plugin1, $isSelected1, $maxLen);
            } else {
                $left = str_repeat(' ', $maxLen + 9);
            }
            
            // Columna derecha
            if (isset($col2[$i])) {
                $plugin2 = $col2[$i];
                $isSelected2 = isset($this->activationOrder[$plugin2]);
                $right = $this->formatPlugin($num2, $plugin2, $isSelected2, $maxLen);
            } else {
                $right = '';
            }
            
            $output->writeln("{$left}\t{$right}");
        }
    }

    private function formatPlugin(int $num, string $plugin, bool $isSelected, int $maxLen): string
    {
        if ($isSelected) {
            // Plugin seleccionado: verde con checkmark
            $position = $this->activationOrder[$plugin];
            return sprintf(
                "  <fg=cyan>[</><fg=yellow>%2d</><fg=cyan>]</> <fg=green>%-{$maxLen}s ✓ [%d]</>",
                $num,
                $plugin,
                $position
            );
        } else {
            // Plugin disponible: números amarillos
            return sprintf(
                "  <fg=cyan>[</><fg=yellow>%2d</><fg=cyan>]</> <fg=white>%-{$maxLen}s</>",
                $num,
                $plugin
            );
        }
    }

    private function displaySeparator(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>' . str_repeat('━', 60) . '</>');
    }

    private function displayCurrentOrder(OutputInterface $output): void
    {
        $output->writeln('');
        if (empty($this->activationOrder)) {
            $output->writeln('<comment>Orden actual: [vacío]</comment>');
        } else {
            $output->writeln('<fg=yellow>Orden actual (' . count($this->activationOrder) . ' plugins):</>');
            $output->writeln('');
            
            // Ordenar por posición
            asort($this->activationOrder);
            $ordered = [];
            foreach ($this->activationOrder as $plugin => $position) {
                $ordered[] = [$position, $plugin];
            }
            
            // Calcular longitud máxima para alineación
            $maxLen = 0;
            foreach ($ordered as $item) {
                $len = strlen($item[1]);
                if ($len > $maxLen) $maxLen = $len;
            }
            
            // Mostrar en 2 columnas
            $half = (int)ceil(count($ordered) / 2);
            for ($i = 0; $i < $half; $i++) {
                $left = '';
                $right = '';
                
                if (isset($ordered[$i])) {
                    $pos = $ordered[$i][0];
                    $plugin = $ordered[$i][1];
                    $left = sprintf("  <fg=cyan>[%2d]</> <fg=green>%-{$maxLen}s</>", $pos, $plugin);
                }
                
                if (isset($ordered[$i + $half])) {
                    $pos = $ordered[$i + $half][0];
                    $plugin = $ordered[$i + $half][1];
                    $right = sprintf("  <fg=cyan>[%2d]</> <fg=green>%s</>", $pos, $plugin);
                }
                
                if (!empty($right)) {
                    $output->writeln("{$left}\t{$right}");
                } else {
                    $output->writeln($left);
                }
            }
        }
    }

    private function displayHelp(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=yellow>Comandos:</>');
        $output->writeln('  <fg=cyan><números></>');
        $output->writeln('    <comment>Ej: 44,10,18 o 7-12 (agregar plugins)</comment>');
        $output->writeln('  <fg=cyan>list</>');
        $output->writeln('    <comment>Ver orden actual</comment>');
        $output->writeln('  <fg=cyan>remove <pos></>');
        $output->writeln('    <comment>Quitar posición del orden</comment>');
        $output->writeln('  <fg=cyan>clear</>');
        $output->writeln('    <comment>Limpiar todo el orden</comment>');
        $output->writeln('  <fg=cyan>save</>');
        $output->writeln('    <comment>Guardar y salir</comment>');
        $output->writeln('  <fg=cyan>cancel</>');
        $output->writeln('    <comment>Cancelar sin guardar</comment>');
    }

    private function displayCompactHelp(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=yellow>Comandos:</> <fg=cyan><nums></> | <fg=cyan>list</> | <fg=cyan>remove <pos></> | <fg=cyan>clear</> | <fg=cyan>save</> | <fg=cyan>cancel</>');
    }

    private function processCommand(string $command, OutputInterface $output): ?string
    {
        $parts = explode(' ', $command);
        $action = strtolower($parts[0]);

        switch ($action) {
            case 'save':
                return 'save';
            
            case 'cancel':
                return 'cancel';
            
            case 'list':
                $this->displayCurrentOrder($output);
                $this->displayCompactHelp($output);
                return null;
            
            case 'clear':
                $this->activationOrder = [];
                $output->writeln('<info>✓ Orden limpiado</info>');
                $this->refreshDisplay($output);
                $this->displayCompactHelp($output);
                return null;
            
            case 'remove':
                if (!isset($parts[1])) {
                    $output->writeln('<error>Uso: remove <posición></error>');
                    $this->displayCompactHelp($output);
                    return null;
                }
                $this->removePosition((int)$parts[1], $output);
                return null;
            
            default:
                // Intentar parsear como números/rangos
                $this->addPlugins($command, $output);
                return null;
        }
    }

    private function refreshDisplay(OutputInterface $output): void
    {
        $output->writeln('');
        $this->displayPluginsInColumns($output);
        $this->displaySeparator($output);
        $this->displayCurrentOrder($output);
    }

    private function addPlugins(string $input, OutputInterface $output): void
    {
        $indices = $this->parseIndices($input);
        
        if (empty($indices)) {
            $output->writeln('<error>Formato inválido. Usa: 1,2,3 o 1-5</error>');
            $this->displayCompactHelp($output);
            return;
        }

        $added = 0;
        $position = count($this->activationOrder) + 1;

        foreach ($indices as $index) {
            if ($index < 1 || $index > count($this->plugins)) {
                $output->writeln("<error>Índice {$index} fuera de rango</error>");
                continue;
            }

            $plugin = $this->plugins[$index - 1];

            if (isset($this->activationOrder[$plugin])) {
                continue; // Silenciosamente ignorar duplicados
            }

            $this->activationOrder[$plugin] = $position++;
            $added++;
        }

        if ($added > 0) {
            $output->writeln("<info>✓ Agregados {$added} plugin(s)</info>");
            $output->writeln('');
            $this->displayPluginsInColumns($output);
            $this->displaySeparator($output);
            $this->displayCurrentOrder($output);
            $this->displayCompactHelp($output);
        }
    }

    private function parseIndices(string $input): array
    {
        $indices = [];
        $parts = explode(',', $input);

        foreach ($parts as $part) {
            $part = trim($part);
            
            if (strpos($part, '-') !== false) {
                // Rango: 7-12
                list($start, $end) = explode('-', $part);
                $start = (int)trim($start);
                $end = (int)trim($end);
                
                if ($start > 0 && $end >= $start) {
                    for ($i = $start; $i <= $end; $i++) {
                        $indices[] = $i;
                    }
                }
            } elseif (is_numeric($part)) {
                // Número simple
                $indices[] = (int)$part;
            }
        }

        return array_unique($indices);
    }

    private function removePosition(int $position, OutputInterface $output): void
    {
        $plugin = array_search($position, $this->activationOrder);
        
        if ($plugin === false) {
            $output->writeln("<error>Posición {$position} no encontrada</error>");
            $this->displayCompactHelp($output);
            return;
        }

        unset($this->activationOrder[$plugin]);
        
        // Renumerar manteniendo los nombres de plugins
        $plugins = array_keys($this->activationOrder);
        $this->activationOrder = [];
        $pos = 1;
        foreach ($plugins as $p) {
            $this->activationOrder[$p] = $pos++;
        }

        $output->writeln("<info>✓ Removido: {$plugin}</info>");
        $this->refreshDisplay($output);
        $this->displayCompactHelp($output);
    }

    private function askDependencies(InputInterface $input, OutputInterface $output, $helper): void
    {
        $output->writeln('');
        $question = new ConfirmationQuestion('<fg=yellow>¿Definir dependencias? [y/N]:</> ', false);
        
        if (!$helper->ask($input, $output, $question)) {
            return;
        }

        $output->writeln('');
        $output->writeln('<comment>Para cada plugin, ingresa los números de sus dependencias separados por comas.</comment>');
        $output->writeln('<comment>Presiona Enter si no tiene dependencias.</comment>');
        $output->writeln('');

        foreach ($this->activationOrder as $plugin => $position) {
            $output->writeln("<fg=cyan>Plugin [{$position}]: {$plugin}</>");
            $question = new Question('  <fg=yellow>Dependencias (números):</> ');
            $deps = trim($helper->ask($input, $output, $question));

            if (!empty($deps)) {
                $depIndices = $this->parseIndices($deps);
                $depPlugins = [];

                foreach ($depIndices as $idx) {
                    if ($idx < 1 || $idx > count($this->plugins)) {
                        continue;
                    }
                    $depPlugin = $this->plugins[$idx - 1];
                    if (isset($this->activationOrder[$depPlugin]) && $depPlugin !== $plugin) {
                        $depPlugins[] = $depPlugin;
                    }
                }

                if (!empty($depPlugins)) {
                    $this->dependencies[$plugin] = $depPlugins;
                    $output->writeln('  <info>✓ Dependencias: ' . implode(', ', $depPlugins) . '</info>');
                }
            }
            $output->writeln('');
        }
    }

    private function calculateOrderWithDependencies(): array
    {
        $order = $this->activationOrder;
        $changed = true;
        $maxIterations = 100;
        $iteration = 0;

        while ($changed && $iteration < $maxIterations) {
            $changed = false;
            $iteration++;

            foreach ($this->dependencies as $plugin => $deps) {
                foreach ($deps as $dep) {
                    if (isset($order[$dep]) && isset($order[$plugin])) {
                        if ($order[$dep] > $order[$plugin]) {
                            $temp = $order[$dep];
                            $order[$dep] = $order[$plugin];
                            $order[$plugin] = $temp;
                            $changed = true;
                        }
                    }
                }
            }
        }

        asort($order);
        $position = 1;
        $finalOrder = [];
        foreach ($order as $plugin => $oldPos) {
            $finalOrder[$plugin] = $position++;
        }

        return $finalOrder;
    }

    private function saveActivationOrder(InputInterface $input, OutputInterface $output, $helper, array $order): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>Orden final calculado:</>');
        $output->writeln('');

        asort($order);
        foreach ($order as $plugin => $position) {
            $depInfo = isset($this->dependencies[$plugin]) ? ' <fg=yellow>↳ ' . implode(', ', $this->dependencies[$plugin]) . '</>' : '';
            $output->writeln("  <fg=cyan>[{$position}]</> <fg=green>{$plugin}</>{$depInfo}");
        }

        $output->writeln('');
        $defaultName = 'activation-order-' . date('Ymd-His') . '.json';
        $filename = $helper->ask($input, $output, new Question(
            "<fg=yellow>Nombre del archivo [{$defaultName}]:</> ",
            $defaultName
        ));

        if (!str_ends_with($filename, '.json')) {
            $filename .= '.json';
        }

        $configDir = getcwd() . '/config/plugins';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $filepath = $configDir . '/' . $filename;
        $data = [
            'activation_order' => $order,
            'dependencies' => $this->dependencies,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $output->writeln('');
        $output->writeln("<info>✓ Orden de activación guardado en: {$filepath}</info>");
        $output->writeln('');
    }
}
