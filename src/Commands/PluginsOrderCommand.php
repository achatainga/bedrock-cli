<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
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
             ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Archivo de configuración personalizado');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configFile = $this->getConfigFile($input);
        
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
}
