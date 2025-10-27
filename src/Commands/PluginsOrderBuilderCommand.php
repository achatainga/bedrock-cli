<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Roots\BedrockCli\Services\UnzipService;

class PluginsOrderBuilderCommand extends Command
{
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

        $plugins = array_filter(scandir($pluginsDir), function($item) use ($pluginsDir) {
            return $item !== '.' && $item !== '..' && is_dir($pluginsDir . '/' . $item);
        });

        if (empty($plugins)) {
            $output->writeln('<comment>No hay plugins instalados</comment>');
            return Command::FAILURE;
        }

        $plugins = array_values($plugins);
        $activationOrder = [];
        $dependencies = [];
        $position = 1;

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Constructor de Orden de Activación de Plugins       </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln('<comment>Selecciona plugins en el orden que deseas activarlos.</comment>');
        $output->writeln('<comment>Puedes definir dependencias para cada plugin.</comment>');
        $output->writeln('');

        while (!empty($plugins)) {
            // Mostrar plugins disponibles en 2 columnas
            $this->displayPluginsInColumns($output, $plugins, $activationOrder);

            $choices = [];
            foreach ($plugins as $index => $plugin) {
                $choices[(string)($index + 1)] = "<fg=green>{$plugin}</>";
            }
            $choices['s'] = '<fg=yellow>Guardar y salir</>';
            $choices['0'] = '<fg=red>Cancelar</>';

            $question = new ChoiceQuestion(
                '<fg=yellow>Selecciona el siguiente plugin (o "s" para guardar):</>', 
                $choices, 
                's'
            );
            $question->setAutocompleterValues(null);
            $choice = $helper->ask($input, $output, $question);

            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();

            if ($choice === '<fg=yellow>Guardar y salir</>') {
                break;
            }

            if ($choice === '<fg=red>Cancelar</>') {
                $output->writeln('<comment>Operación cancelada</comment>');
                return Command::SUCCESS;
            }

            // Extraer nombre del plugin
            preg_match('/<fg=green>(.*?)<\/>/', $choice, $matches);
            $selectedPlugin = $matches[1] ?? null;

            if (!$selectedPlugin) {
                continue;
            }

            // Preguntar por dependencias
            $output->writeln('');
            $output->writeln("<info>Plugin seleccionado: {$selectedPlugin}</info>");
            
            $depQuestion = new ChoiceQuestion(
                '<fg=yellow>¿Tiene dependencias? (plugins que deben activarse antes):</>', 
                ['no' => '<fg=cyan>No tiene dependencias</>', 'si' => '<fg=yellow>Sí, seleccionar dependencias</>'],
                'no'
            );
            $depQuestion->setAutocompleterValues(null);
            $hasDeps = $helper->ask($input, $output, $depQuestion);

            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();

            $pluginDeps = [];
            if ($hasDeps === '<fg=yellow>Sí, seleccionar dependencias</>') {
                $pluginDeps = $this->selectDependencies($input, $output, $helper, $selectedPlugin, $plugins, $activationOrder);
            }

            // Agregar al orden
            $activationOrder[$selectedPlugin] = $position;
            if (!empty($pluginDeps)) {
                $dependencies[$selectedPlugin] = $pluginDeps;
            }
            $position++;

            // Remover de la lista
            $plugins = array_values(array_filter($plugins, fn($p) => $p !== $selectedPlugin));

            $output->writeln('');
            $output->writeln("<info>✓ {$selectedPlugin} agregado en posición {$activationOrder[$selectedPlugin]}</info>");
            $output->writeln('');
        }

        if (empty($activationOrder)) {
            $output->writeln('<comment>No se seleccionaron plugins</comment>');
            return Command::SUCCESS;
        }

        // Recalcular orden basado en dependencias
        $finalOrder = $this->calculateOrderWithDependencies($activationOrder, $dependencies);

        // Guardar
        $this->saveActivationOrder($input, $output, $helper, $finalOrder, $dependencies);

        return Command::SUCCESS;
    }

    private function displayPluginsInColumns(OutputInterface $output, array $plugins, array $selected): void
    {
        $output->writeln('<fg=cyan>Plugins disponibles:</>');
        $output->writeln('');

        $half = (int)ceil(count($plugins) / 2);
        $col1 = array_slice($plugins, 0, $half);
        $col2 = array_slice($plugins, $half);

        $maxLen = max(array_map('strlen', $plugins));

        for ($i = 0; $i < $half; $i++) {
            $left = isset($col1[$i]) ? sprintf("  [%2d] %-{$maxLen}s", $i + 1, $col1[$i]) : str_repeat(' ', $maxLen + 7);
            $right = isset($col2[$i]) ? sprintf("  [%2d] %s", $i + $half + 1, $col2[$i]) : '';
            $output->writeln("<fg=green>{$left}</>\t<fg=green>{$right}</>");
        }

        if (!empty($selected)) {
            $output->writeln('');
            $output->writeln('<fg=yellow>Orden actual: ' . count($selected) . ' plugins seleccionados</>' );
        }
        $output->writeln('');
    }

    private function selectDependencies(InputInterface $input, OutputInterface $output, $helper, string $plugin, array $availablePlugins, array $alreadySelected): array
    {
        $deps = [];
        $candidates = array_merge(array_keys($alreadySelected), $availablePlugins);
        $candidates = array_filter($candidates, fn($p) => $p !== $plugin);

        if (empty($candidates)) {
            $output->writeln('<comment>No hay otros plugins disponibles como dependencias</comment>');
            return [];
        }

        $output->writeln('');
        $output->writeln("<comment>Selecciona las dependencias de {$plugin}:</comment>");
        $output->writeln('<comment>(Plugins que deben activarse ANTES de este)</comment>');
        $output->writeln('');

        while (true) {
            $choices = [];
            foreach ($candidates as $index => $candidate) {
                if (!in_array($candidate, $deps)) {
                    $choices[(string)($index + 1)] = "<fg=cyan>{$candidate}</>";
                }
            }
            $choices['0'] = '<fg=green>Terminar selección</>';

            if (count($choices) === 1) {
                break;
            }

            $question = new ChoiceQuestion(
                '<fg=yellow>Selecciona una dependencia (o 0 para terminar):</>', 
                $choices, 
                '0'
            );
            $question->setAutocompleterValues(null);
            $choice = $helper->ask($input, $output, $question);

            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();

            if ($choice === '<fg=green>Terminar selección</>') {
                break;
            }

            preg_match('/<fg=cyan>(.*?)<\/>/', $choice, $matches);
            $dep = $matches[1] ?? null;

            if ($dep) {
                $deps[] = $dep;
                $output->writeln("<info>✓ Dependencia agregada: {$dep}</info>");
            }
        }

        return $deps;
    }

    private function calculateOrderWithDependencies(array $order, array $dependencies): array
    {
        // Ajustar posiciones basadas en dependencias
        $changed = true;
        $maxIterations = 100;
        $iteration = 0;

        while ($changed && $iteration < $maxIterations) {
            $changed = false;
            $iteration++;

            foreach ($dependencies as $plugin => $deps) {
                foreach ($deps as $dep) {
                    if (isset($order[$dep]) && isset($order[$plugin])) {
                        // Si la dependencia tiene mayor posición, intercambiar
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

        // Renumerar secuencialmente
        asort($order);
        $position = 1;
        $finalOrder = [];
        foreach ($order as $plugin => $oldPos) {
            $finalOrder[$plugin] = $position++;
        }

        return $finalOrder;
    }

    private function saveActivationOrder(InputInterface $input, OutputInterface $output, $helper, array $order, array $dependencies): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>Orden final calculado:</>');
        $output->writeln('');

        asort($order);
        foreach ($order as $plugin => $position) {
            $depInfo = isset($dependencies[$plugin]) ? ' <fg=yellow>↳ ' . implode(', ', $dependencies[$plugin]) . '</>' : '';
            $output->writeln("  [{$position}] <fg=green>{$plugin}</>{$depInfo}");
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
            'dependencies' => $dependencies,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $output->writeln('');
        $output->writeln("<info>✓ Orden de activación guardado en: {$filepath}</info>");
        $output->writeln('');
    }
}
