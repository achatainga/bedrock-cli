<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class OrderCommand extends Command
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
        $orderData = $config['activation_order'] ?? [];
        
        // Ordenar por posición
        asort($orderData);
        $order = array_keys($orderData);

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

        $activated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($order as $index => $slug) {
            $position = $index + 1;
            $output->write("  <fg=cyan>[{$position}/{$count}]</> <comment>Activando {$slug}...</comment>");
            
            // Activar UNO POR UNO usando wp plugin activate
            $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'plugin', 'activate', $slug]);
            $process->setTimeout(60);
            $process->run();
            
            $outputText = trim($process->getOutput());
            
            if (str_contains($outputText, 'already active')) {
                $output->write("\r  <fg=cyan>[{$position}/{$count}]</> <comment>⊘ {$slug} (ya activo)</comment>" . str_repeat(' ', 20) . "\n");
                $skipped++;
            } elseif ($process->isSuccessful() && str_contains($outputText, 'Success')) {
                $output->write("\r  <fg=cyan>[{$position}/{$count}]</> <info>✓ {$slug}</info>" . str_repeat(' ', 20) . "\n");
                $activated++;
            } else {
                $errorMsg = $process->getErrorOutput() ?: $outputText;
                $output->write("\r  <fg=cyan>[{$position}/{$count}]</> <error>✗ {$slug}</error>" . str_repeat(' ', 20) . "\n");
                $output->writeln("      <fg=red>└─</> {$errorMsg}");
                $failed++;
                $errors[] = ['plugin' => $slug, 'message' => $errorMsg];
            }
        }

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>─── RESUMEN ───</>');
        $output->writeln('');
        $output->writeln("  <info>✓ Activados: {$activated}</info>");
        $output->writeln("  <comment>⊘ Ya activos: {$skipped}</comment>");
        $output->writeln("  <error>✗ Fallidos: {$failed}</error>");
        
        if (!empty($errors)) {
            $output->writeln('');
            $output->writeln('<fg=red;options=bold>⚠️  ERRORES DETECTADOS:</>');
            $output->writeln('');
            foreach ($errors as $error) {
                $output->writeln("  <fg=red>•</> <fg=yellow>{$error['plugin']}</>: {$error['message']}");
            }
        }
        
        $output->writeln('');
        
        if ($failed > 0) {
            $output->writeln('<fg=yellow>✓ Activación completada con errores</>');
            return Command::FAILURE;
        }
        
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
