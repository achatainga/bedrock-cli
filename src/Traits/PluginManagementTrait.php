<?php

namespace Roots\BedrockCli\Traits;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

trait PluginManagementTrait
{
    /**
     * Muestra menú CRUD intuitivo para gestión de plugins
     * Número → Abre submenú de ese plugin
     * [A] → Agregar nuevo
     */
    protected function managePluginsInteractive(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        while (true) {
            $this->displayPluginsList($profile, $output);
            
            $question = new Question('> ');
            $action = strtoupper(trim($helper->ask($input, $output, $question)));
            
            if ($action === '0') {
                break;
            }
            
            if ($action === 'A') {
                $this->addNewPlugin($profile, $input, $output, $helper);
            } elseif (is_numeric($action)) {
                $num = (int)$action;
                $this->editPluginByNumber($profile, $num, $input, $output, $helper);
            }
            
            $output->writeln('');
        }
    }
    
    /**
     * Muestra lista numerada de todos los plugins
     */
    private function displayPluginsList(array $profile, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   📦 PLUGINS                       <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $publicPlugins = $profile['plugins']['public'] ?? [];
        $premiumPlugins = $profile['plugins']['premium'] ?? [];
        $customPlugins = $profile['plugins']['custom'] ?? [];
        
        $index = 1;
        
        if (!empty($publicPlugins)) {
            $output->writeln('<fg=green>🌐 PÚBLICOS (' . count($publicPlugins) . ')</>');
            foreach ($publicPlugins as $plugin) {
                $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                $version = is_array($plugin) ? ($plugin['version'] ?? '*') : '*';
                $muBadge = (is_array($plugin) && ($plugin['mu_plugin'] ?? false)) ? ' <fg=yellow>[MU]</>' : '';
                $output->writeln("  <fg=cyan>[{$index}]</> {$slug}:{$version}{$muBadge}");
                $index++;
            }
            $output->writeln('');
        }
        
        if (!empty($premiumPlugins)) {
            $output->writeln('<fg=magenta>💎 PREMIUM (' . count($premiumPlugins) . ')</>');
            foreach ($premiumPlugins as $plugin) {
                $muBadge = ($plugin['mu_plugin'] ?? false) ? ' <fg=yellow>[MU]</>' : '';
                $output->writeln("  <fg=cyan>[{$index}]</> {$plugin['name']}:{$plugin['version']}{$muBadge}");
                $index++;
            }
            $output->writeln('');
        }
        
        if (!empty($customPlugins)) {
            $output->writeln('<fg=yellow>🔧 CUSTOM (' . count($customPlugins) . ')</>');
            foreach ($customPlugins as $slug) {
                $output->writeln("  <fg=cyan>[{$index}]</> {$slug}");
                $index++;
            }
            $output->writeln('');
        }
        
        if ($index === 1) {
            $output->writeln('<comment>(ningún plugin configurado)</comment>');
            $output->writeln('');
        }
        
        $output->writeln('  <fg=cyan>[A]</> ➕ Agregar nuevo');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
    }
    
    /**
     * Edita un plugin específico por su número
     */
    private function editPluginByNumber(array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
    {
        $plugin = $this->getPluginByNumber($profile, $num);
        
        if (!$plugin) {
            $output->writeln('<error>Plugin no encontrado</error>');
            return;
        }
        
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln("<fg=cyan>║</>   ✏️  {$plugin['display']}");
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $isMU = $plugin['mu_plugin'] ?? false;
        
        $output->writeln("  <fg=cyan>[1]</> Cambiar versión (actual: {$plugin['version']})");
        
        if ($plugin['type'] !== 'custom') {
            $muLabel = $isMU ? 'Desmarcar' : 'Marcar';
            $output->writeln("  <fg=cyan>[2]</> 🔒 {$muLabel} como MU-Plugin");
        }
        
        $output->writeln('  <fg=cyan>[3]</> 🗑️  Eliminar este plugin');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
        
        $question = new Question('> ');
        $choice = trim($helper->ask($input, $output, $question));
        
        if ($choice === '1') {
            $this->changePluginVersion($profile, $num, $input, $output, $helper);
        } elseif ($choice === '2' && $plugin['type'] !== 'custom') {
            $this->toggleMUPlugin($profile, $num);
            $status = $isMU ? 'desmarcado' : 'marcado';
            $output->writeln("<info>✓ Plugin {$status} como MU-Plugin</info>");
        } elseif ($choice === '3') {
            $this->deletePluginByNumber($profile, $num, $output);
        }
    }
    
    /**
     * Obtiene plugin por número de índice
     */
    private function getPluginByNumber(array $profile, int $num): ?array
    {
        $index = 1;
        
        foreach ($profile['plugins']['public'] ?? [] as $plugin) {
            if ($index === $num) {
                $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                $version = is_array($plugin) ? ($plugin['version'] ?? '*') : '*';
                $muPlugin = is_array($plugin) ? ($plugin['mu_plugin'] ?? false) : false;
                return [
                    'type' => 'public',
                    'slug' => $slug,
                    'version' => $version,
                    'mu_plugin' => $muPlugin,
                    'display' => "{$slug}:{$version}"
                ];
            }
            $index++;
        }
        
        foreach ($profile['plugins']['premium'] ?? [] as $plugin) {
            if ($index === $num) {
                return [
                    'type' => 'premium',
                    'slug' => $plugin['name'],
                    'version' => $plugin['version'],
                    'mu_plugin' => $plugin['mu_plugin'] ?? false,
                    'display' => "{$plugin['name']}:{$plugin['version']}"
                ];
            }
            $index++;
        }
        
        foreach ($profile['plugins']['custom'] ?? [] as $slug) {
            if ($index === $num) {
                return [
                    'type' => 'custom',
                    'slug' => $slug,
                    'version' => '*',
                    'mu_plugin' => false,
                    'display' => $slug
                ];
            }
            $index++;
        }
        
        return null;
    }
    
    /**
     * Cambia versión de un plugin
     */
    private function changePluginVersion(array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
    {
        $plugin = $this->getPluginByNumber($profile, $num);
        $question = new Question("Nueva versión [{$plugin['version']}]: ", $plugin['version']);
        $newVersion = $helper->ask($input, $output, $question);
        
        $index = 1;
        
        foreach ($profile['plugins']['public'] as &$p) {
            if ($index === $num) {
                if (is_array($p)) {
                    $p['version'] = $newVersion;
                } else {
                    $p = ['slug' => $p, 'version' => $newVersion];
                }
                $output->writeln('<info>✓ Versión actualizada</info>');
                return;
            }
            $index++;
        }
        
        foreach ($profile['plugins']['premium'] as &$p) {
            if ($index === $num) {
                $p['version'] = $newVersion;
                $output->writeln('<info>✓ Versión actualizada</info>');
                return;
            }
            $index++;
        }
    }
    
    /**
     * Marca/desmarca plugin como MU-Plugin
     */
    private function toggleMUPlugin(array &$profile, int $num): void
    {
        $index = 1;
        
        foreach ($profile['plugins']['public'] as &$plugin) {
            if ($index === $num) {
                if (!is_array($plugin)) {
                    $plugin = ['slug' => $plugin, 'version' => '*'];
                }
                $plugin['mu_plugin'] = !($plugin['mu_plugin'] ?? false);
                return;
            }
            $index++;
        }
        
        foreach ($profile['plugins']['premium'] as &$plugin) {
            if ($index === $num) {
                $plugin['mu_plugin'] = !($plugin['mu_plugin'] ?? false);
                return;
            }
            $index++;
        }
    }
    
    /**
     * Elimina plugin por número
     */
    private function deletePluginByNumber(array &$profile, int $num, OutputInterface $output): void
    {
        $index = 1;
        
        foreach ($profile['plugins']['public'] as $key => $plugin) {
            if ($index === $num) {
                $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                unset($profile['plugins']['public'][$key]);
                $profile['plugins']['public'] = array_values($profile['plugins']['public']);
                $output->writeln("<info>✓ Plugin '{$slug}' eliminado</info>");
                return;
            }
            $index++;
        }
        
        foreach ($profile['plugins']['premium'] as $key => $plugin) {
            if ($index === $num) {
                unset($profile['plugins']['premium'][$key]);
                $profile['plugins']['premium'] = array_values($profile['plugins']['premium']);
                $output->writeln("<info>✓ Plugin '{$plugin['name']}' eliminado</info>");
                return;
            }
            $index++;
        }
        
        foreach ($profile['plugins']['custom'] as $key => $slug) {
            if ($index === $num) {
                unset($profile['plugins']['custom'][$key]);
                $profile['plugins']['custom'] = array_values($profile['plugins']['custom']);
                $output->writeln("<info>✓ Plugin '{$slug}' eliminado</info>");
                return;
            }
            $index++;
        }
    }
    
    /**
     * Agrega nuevo plugin (debe implementarse en el comando que use el trait)
     */
    abstract protected function addNewPlugin(array &$profile, InputInterface $input, OutputInterface $output, $helper): void;
}
