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
        $dependencies = $config['dependencies'] ?? [];
        
        asort($orderData);

        if (empty($orderData)) {
            $output->writeln('<comment>No hay plugins en el orden de activación</comment>');
            return Command::SUCCESS;
        }

        // Detectar REST API
        $token = $this->getBedrockToken();
        $url = $this->getWordPressUrl();
        $useRestApi = $token && $url;

        if ($useRestApi) {
            $output->writeln('');
            $output->writeln('<fg=green>✓ REST API detectada - Activación rápida habilitada</>');
        }

        // Validar que plugins existen
        $output->writeln('');
        
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        $validated = null;
        
        for ($i = 0; $i < 10; $i++) {
            $output->write("\r<comment>Validando plugins...</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            
            if ($i === 0) {
                $validated = $this->validatePluginsExist($orderData, $output);
            }
            
            usleep(50000);
        }
        
        $orderData = $validated['order'];
        $missing = $validated['missing'];
        
        if (!empty($missing)) {
            $output->write("\r<comment>Validando plugins...</comment> <fg=yellow>⚠</> " . str_repeat(' ', 10) . "\n");
            $output->writeln('');
            $output->writeln('<fg=yellow>Plugins no encontrados (serán omitidos):</>'); 
            foreach ($missing as $slug) {
                $output->writeln("  • {$slug}");
            }
            $output->writeln('');
        } else {
            $output->write("\r<comment>Validando plugins...</comment> <info>✓</info>" . str_repeat(' ', 10) . "\n");
        }
        
        if (empty($orderData)) {
            $output->writeln('<comment>No hay plugins válidos para activar</comment>');
            return Command::SUCCESS;
        }

        // Calcular niveles de dependencias
        $levels = $this->calculateDependencyLevels($orderData, $dependencies);
        
        $totalPlugins = count($orderData);
        $output->writeln('');
        $output->writeln('<fg=yellow;options=bold>Aplicando Orden de Activación por Niveles:</>');
        $output->writeln('');
        $output->writeln("<info>🚀 {$totalPlugins} plugins en " . count($levels) . " nivel(es)</info>");
        $output->writeln('<comment>Estrategia: Activar plugins sin dependencias primero</comment>');
        $output->writeln('');
        
        $activated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($levels as $levelNum => $levelPlugins) {
            $count = count($levelPlugins);
            $output->writeln("<fg=cyan>Nivel {$levelNum}:</> {$count} plugin(s)");
            
            // Mostrar plugins del nivel
            foreach ($levelPlugins as $plugin) {
                $deps = $dependencies[$plugin] ?? [];
                $depInfo = !empty($deps) ? ' <fg=gray>← ' . implode(', ', $deps) . '</>' : '';
                $output->writeln("  • {$plugin}{$depInfo}");
            }
            
            // Intentar REST API primero
            $results = null;
            if ($useRestApi) {
                $results = $this->activateViaRestApi($url, $token, $levelPlugins, $output);
            }
            
            // Fallback a wp eval si REST falla
            if (!$results) {
                if ($useRestApi) {
                    $output->writeln('  <fg=yellow>⚠ REST API falló, usando wp eval...</>');
                }
                $results = $this->activateViaWpEval($levelPlugins, $levelNum, $output);
            }
            
            if (!$results) {
                $output->writeln("<error>Error al ejecutar nivel {$levelNum}</error>");
                return Command::FAILURE;
            }
            
            // Procesar resultados del nivel
            foreach ($results as $result) {
                if ($result['status'] === 'skip') {
                    $output->writeln("    <comment>⊘ {$result['plugin']} (ya activo)</comment>");
                    $skipped++;
                } elseif ($result['status'] === 'ok') {
                    $output->writeln("    <info>✓ {$result['plugin']}</info>");
                    $activated++;
                } elseif ($result['status'] === 'error') {
                    $output->writeln("    <error>✗ {$result['plugin']}</error>");
                    $output->writeln("      <fg=red>└─</> {$result['msg']}");
                    $failed++;
                    $errors[] = ['plugin' => $result['plugin'], 'message' => $result['msg']];
                }
            }
            
            // Si hay errores en este nivel, DETENER
            if ($failed > 0) {
                $output->writeln('');
                $output->writeln("<error>⚠️  Activación detenida por errores en nivel {$levelNum}</error>");
                break;
            }
            
            $output->writeln('');
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

    protected function calculateDependencyLevels(array $order, array $dependencies): array
    {
        $levels = [];
        $processed = [];
        $currentLevel = 0;
        
        while (count($processed) < count($order)) {
            $levelPlugins = [];
            
            foreach ($order as $plugin => $position) {
                if (isset($processed[$plugin])) {
                    continue;
                }
                
                $deps = $dependencies[$plugin] ?? [];
                
                $allDepsProcessed = true;
                foreach ($deps as $dep) {
                    if (!isset($processed[$dep])) {
                        $allDepsProcessed = false;
                        break;
                    }
                }
                
                if ($allDepsProcessed) {
                    $levelPlugins[] = $plugin;
                    $processed[$plugin] = true;
                }
            }
            
            if (empty($levelPlugins)) {
                foreach ($order as $plugin => $position) {
                    if (!isset($processed[$plugin])) {
                        $levelPlugins[] = $plugin;
                        $processed[$plugin] = true;
                    }
                }
            }
            
            $levels[$currentLevel] = $levelPlugins;
            $currentLevel++;
        }
        
        return $levels;
    }

    protected function validatePluginsExist(array $orderData, OutputInterface $output): array
    {
        $pluginsDir = getcwd() . '/web/app/plugins';
        
        if (!is_dir($pluginsDir)) {
            return ['order' => $orderData, 'missing' => []];
        }
        
        $validated = [];
        $missing = [];
        
        foreach ($orderData as $slug => $position) {
            $pluginPath = $pluginsDir . '/' . $slug;
            
            // Soportar symlinks: file_exists() funciona con symlinks
            if (file_exists($pluginPath) && (is_dir($pluginPath) || is_link($pluginPath))) {
                $validated[$slug] = $position;
            } else {
                $missing[] = $slug;
            }
        }
        
        return ['order' => $validated, 'missing' => $missing];
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

    protected function runWithSpinner(Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        $startTime = microtime(true);
        
        $process->start();
        
        while ($process->isRunning()) {
            $elapsed = round(microtime(true) - $startTime, 1);
            $output->write("\r  <comment>{$message}...</comment> <fg=cyan>{$frames[$frameIndex]}</> <fg=gray>({$elapsed}s)</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(100000); // 100ms
        }
        
        $elapsed = round(microtime(true) - $startTime, 1);
        
        if ($process->isSuccessful()) {
            $output->write("\r  <comment>{$message}...</comment> <info>✓</info> <fg=gray>({$elapsed}s)</>" . str_repeat(' ', 10) . "\n");
        } else {
            $output->write("\r  <comment>{$message}...</comment> <error>✗</error> <fg=gray>({$elapsed}s)</>" . str_repeat(' ', 10) . "\n");
        }
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
        
        if ($process->isSuccessful()) {
            $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
        } else {
            $output->write("\r<comment>{$message}</comment> <error>✗</error>\n");
        }
    }

    protected function getBedrockToken(): ?string
    {
        $php = "echo get_option('bedrock_cli_token');";
        
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $process->setTimeout(10);
        $process->run();
        
        if (!$process->isSuccessful()) {
            return null;
        }
        
        $token = trim($process->getOutput());
        return !empty($token) ? $token : null;
    }

    protected function getWordPressUrl(): ?string
    {
        $envFile = getcwd() . '/.env';
        
        if (!file_exists($envFile)) {
            return null;
        }
        
        $content = file_get_contents($envFile);
        
        if (preg_match('/^WP_HOME=(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }

    protected function activateViaRestApi(string $url, string $token, array $plugins, OutputInterface $output): ?array
    {
        $endpoint = rtrim($url, '/') . '/wp-json/bedrock-cli/v1/plugins/activate';
        
        $data = json_encode(['plugins' => $plugins]);
        
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Bedrock-Token: ' . $token,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['results'])) {
            return null;
        }
        
        // Convertir formato REST a formato wp eval
        $results = [];
        foreach ($data['results'] as $slug => $result) {
            if (isset($result['already_active']) && $result['already_active']) {
                $results[] = ['plugin' => $slug, 'status' => 'skip'];
            } elseif ($result['success']) {
                $results[] = ['plugin' => $slug, 'status' => 'ok'];
            } else {
                $results[] = [
                    'plugin' => $slug,
                    'status' => 'error',
                    'msg' => $result['error'] ?? 'Unknown error'
                ];
            }
        }
        
        return $results;
    }

    protected function activateViaWpEval(array $levelPlugins, int $levelNum, OutputInterface $output): ?array
    {
        $pluginsB64 = base64_encode(json_encode($levelPlugins));
        $php = <<<'PHP'
$plugins = json_decode(base64_decode('{PLUGINS_B64}'), true);
$results = [];

foreach ($plugins as $slug) {
    $pluginDir = WP_PLUGIN_DIR . '/' . $slug;
    $pluginFile = null;
    
    if (file_exists($pluginDir . '/' . $slug . '.php')) {
        $pluginFile = $slug . '/' . $slug . '.php';
    } else {
        foreach (glob($pluginDir . '/*.php') as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'Plugin Name:') !== false) {
                $pluginFile = $slug . '/' . basename($file);
                break;
            }
        }
    }
    
    if (!$pluginFile) {
        $results[] = ['plugin' => $slug, 'status' => 'error', 'msg' => 'Plugin file not found'];
        continue;
    }
    
    if (is_plugin_active($pluginFile)) {
        $results[] = ['plugin' => $slug, 'status' => 'skip'];
        continue;
    }
    
    $result = activate_plugin($pluginFile, '', false, true);
    
    if (is_wp_error($result)) {
        $results[] = [
            'plugin' => $slug, 
            'status' => 'error', 
            'msg' => $result->get_error_message()
        ];
    } else {
        $results[] = ['plugin' => $slug, 'status' => 'ok'];
    }
}

echo json_encode($results);
PHP;
        
        $php = str_replace('{PLUGINS_B64}', $pluginsB64, $php);

        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'eval', $php]);
        $process->setTimeout(120);
        
        $this->runWithSpinner($process, $output, "Activando nivel {$levelNum}");
        
        if (!$process->isSuccessful()) {
            return null;
        }
        
        $results = json_decode($process->getOutput(), true);
        
        if (!is_array($results)) {
            return null;
        }
        
        return $results;
    }
}
