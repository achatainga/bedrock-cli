<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class PluginsOrderCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('plugins:order')
             ->setDescription('Gestionar orden de activación de plugins')
             ->addArgument('action', InputArgument::OPTIONAL, 'Acción: list|save|activate', 'save')
             ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Archivo de configuración personalizado');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        $configFile = $this->getConfigFile($input);
        
        return match($action) {
            'list' => $this->listPlugins($configFile, $output),
            'save' => $this->saveOrder($configFile, $output),
            'activate' => $this->activateInOrder($configFile, $output),
            default => $this->error($output, "Acción desconocida: {$action}")
        };
    }

    protected function saveOrder(string $configFile, OutputInterface $output): int
    {
        
        $php = <<<'PHP'
$plugins = [];
$pluginsDir = ABSPATH . '../app/plugins';

if (is_dir($pluginsDir)) {
    foreach (scandir($pluginsDir) as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        
        $pluginFile = "{$pluginsDir}/{$dir}/{$dir}.php";
        if (!file_exists($pluginFile)) {
            foreach (glob("{$pluginsDir}/{$dir}/*.php") as $file) {
                $content = file_get_contents($file);
                if (strpos($content, 'Plugin Name:') !== false) {
                    $pluginFile = $file;
                    break;
                }
            }
        }
        
        if (isset($pluginFile) && file_exists($pluginFile)) {
            $active = is_plugin_active(str_replace($pluginsDir . '/', '', $pluginFile));
            $plugins[$dir] = ['file' => $pluginFile, 'active' => $active];
        }
    }
}

echo json_encode($plugins);
PHP;

        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al obtener plugins</error>');
            return Command::FAILURE;
        }

        $plugins = json_decode($process->getOutput(), true);
        $active = array_keys(array_filter($plugins, fn($p) => $p['active']));
        
        $config = [
            'activation_order' => array_flip($active),
            'dependencies' => $this->detectDependencies(array_keys($plugins))
        ];

        $configDir = dirname($configFile);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        
        $count = count($active);
        $output->writeln('');
        $output->writeln("<info>✓ Guardado orden de activación para {$count} plugins</info>");
        $output->writeln("<comment>📄 {$configFile}</comment>");
        
        return Command::SUCCESS;
    }

    protected function getConfigFile(InputInterface $input): string
    {
        $custom = $input->getOption('config');
        
        if ($custom) {
            if (!str_starts_with($custom, '/') && !str_contains($custom, ':\\')) {
                return getcwd() . "/config/plugins/{$custom}";
            }
            return $custom;
        }
        
        return getcwd() . '/config/plugins/activation-order.json';
    }

    protected function listPlugins(string $configFile, OutputInterface $output): int
    {
        if (!file_exists($configFile)) {
            $output->writeln('<comment>No hay archivo de configuración. Ejecuta: plugins:order save</comment>');
            return Command::SUCCESS;
        }

        $config = json_decode(file_get_contents($configFile), true);
        $order = $config['activation_order'] ?? [];
        $deps = $config['dependencies'] ?? [];

        $output->writeln('');

        $php = <<<'PHP'
$plugins = [];
$pluginsDir = ABSPATH . '../app/plugins';

if (is_dir($pluginsDir)) {
    foreach (scandir($pluginsDir) as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        
        $pluginFile = "{$pluginsDir}/{$dir}/{$dir}.php";
        if (!file_exists($pluginFile)) {
            foreach (glob("{$pluginsDir}/{$dir}/*.php") as $file) {
                $content = file_get_contents($file);
                if (strpos($content, 'Plugin Name:') !== false) {
                    $pluginFile = $file;
                    break;
                }
            }
        }
        
        if (isset($pluginFile) && file_exists($pluginFile)) {
            $active = is_plugin_active(str_replace($pluginsDir . '/', '', $pluginFile));
            $plugins[$dir] = $active;
        }
    }
}

echo json_encode($plugins);
PHP;

        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $this->runWithLoader($process, $output, 'Consultando WordPress');
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al obtener plugins</error>');
            return Command::FAILURE;
        }

        $plugins = json_decode($process->getOutput(), true);
        $count = count($plugins);
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>Estado Actual de Plugins:</>');
        $output->writeln('');
        $output->writeln("<info>📦 Total instalados: {$count}</info>");
        $output->writeln('<comment>Leyenda: ✓ = Activo | ○ = Inactivo | [Número] = Orden de carga</comment>');
        $output->writeln('');

        foreach ($plugins as $slug => $active) {
            $status = $active ? '✓' : '○';
            $orderNum = isset($order[$slug]) ? $order[$slug] : '?';
            $pluginDeps = $deps[$slug] ?? [];
            
            $output->writeln("  {$status} [{$orderNum}] {$slug}");
            if (!empty($pluginDeps)) {
                $output->writeln("      ↳ Requiere: " . implode(', ', $pluginDeps));
            }
        }

        $output->writeln('');
        $output->writeln('<comment>Para guardar este orden: plugins:order save</comment>');
        $output->writeln("<comment>📄 Archivo de configuración: {$configFile}</comment>");
        
        return Command::SUCCESS;
    }

    protected function activateInOrder(string $configFile, OutputInterface $output): int
    {
        if (!file_exists($configFile)) {
            $output->writeln('<error>Archivo de configuración no encontrado. Ejecuta: plugins:order save</error>');
            return Command::FAILURE;
        }

        $config = json_decode(file_get_contents($configFile), true);
        $order = array_keys($config['activation_order'] ?? []);

        if (empty($order)) {
            $output->writeln('<comment>No hay plugins en el orden de activación</comment>');
            return Command::SUCCESS;
        }

        $count = count($order);
        $output->writeln('');
        $output->writeln('<fg=yellow;options=bold>Aplicando Orden de Activación:</>');
        $output->writeln('');
        $output->writeln("<info>🚀 Se activarán {$count} plugins en secuencia...</info>");
        $output->writeln('<comment>Los plugins ya activos se omitirán automáticamente.</comment>');
        $output->writeln('');

        $orderJson = json_encode($order);
        $php = <<<PHP
\$order = json_decode('{$orderJson}', true);
\$results = [];

foreach (\$order as \$slug) {
    \$pluginFile = \$slug . '/' . \$slug . '.php';
    
    if (is_plugin_active(\$pluginFile)) {
        \$results[] = ['plugin' => \$slug, 'status' => 'already_active'];
        continue;
    }
    
    \$result = activate_plugin(\$pluginFile);
    
    if (is_wp_error(\$result)) {
        \$results[] = ['plugin' => \$slug, 'status' => 'error', 'message' => \$result->get_error_message()];
    } else {
        \$results[] = ['plugin' => \$slug, 'status' => 'activated'];
    }
}

echo json_encode(\$results);
PHP;

        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $process->setTimeout(300);
        $this->runWithLoader($process, $output, 'Activando plugins');
        $output->writeln('');
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error al activar plugins</error>');
            return Command::FAILURE;
        }

        $results = json_decode($process->getOutput(), true);
        
        foreach ($results as $result) {
            if ($result['status'] === 'already_active') {
                $output->writeln("  <comment>⊘ {$result['plugin']} (ya activo)</comment>");
            } elseif ($result['status'] === 'activated') {
                $output->writeln("  <info>✓ {$result['plugin']}</info>");
            } elseif ($result['status'] === 'error') {
                $output->writeln("  <error>✗ {$result['plugin']}: {$result['message']}</error>");
            }
        }

        $output->writeln('');
        $output->writeln('<info>✓ Activación completa</info>');
        
        return Command::SUCCESS;
    }

    protected function error(OutputInterface $output, string $message): int
    {
        $output->writeln("<error>{$message}</error>");
        return Command::FAILURE;
    }

    protected function detectDependencies(array $plugins): array
    {
        $deps = [];
        $patterns = [
            'woocommerce-' => ['woocommerce'],
            'dt24-' => ['woocommerce'],
        ];

        foreach ($plugins as $slug) {
            foreach ($patterns as $prefix => $requires) {
                if (strpos($slug, $prefix) === 0) {
                    $deps[$slug] = $requires;
                }
            }
        }

        return $deps;
    }

    protected function runWithLoader(Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        
        // Iniciar proceso asíncrono
        $process->start();
        
        // Animar mientras el proceso corre
        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000); // 80ms por frame
        }
        
        // Mostrar resultado final
        if ($process->isSuccessful()) {
            $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
        } else {
            $output->write("\r<comment>{$message}</comment> <error>✗</error>\n");
        }
    }
}
