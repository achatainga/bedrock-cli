<?php

namespace Roots\BedrockCli\Traits;

use Roots\BedrockCli\DTOs\Profile;
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
    protected function managePluginsInteractive(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void
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
    private function displayPluginsList(Profile|array $profile, OutputInterface $output): void
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
    private function editPluginByNumber(Profile|array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
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
            // Regenerar require inmediatamente para reflejar cambio de versión
            if (method_exists($this, 'regenerateRequire')) {
                $this->regenerateRequire($profile);
            }
        } elseif ($choice === '2' && $plugin['type'] !== 'custom') {
            $this->toggleMUPlugin($profile, $num);
            $status = $isMU ? 'desmarcado' : 'marcado';
            $output->writeln("<info>✓ Plugin {$status} como MU-Plugin</info>");
        } elseif ($choice === '3') {
            $this->deletePluginByNumber($profile, $num, $output);
            // Regenerar require después de eliminar plugin
            if (method_exists($this, 'regenerateRequire')) {
                $this->regenerateRequire($profile);
            }
        }
    }
    
    /**
     * Obtiene plugin por número de índice
     */
    private function getPluginByNumber(Profile|array $profile, int $num): ?array
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
    private function changePluginVersion(Profile|array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
    {
        $plugin = $this->getPluginByNumber($profile, $num);
        $question = new Question("Nueva versión [{$plugin['version']}]: ", $plugin['version']);
        $newVersion = $helper->ask($input, $output, $question);
        
        $index = 1;
        $updated = false;
        
        $plugins = $profile['plugins'];
        foreach ($plugins['public'] as $key => $p) {
            if ($index === $num) {
                if (is_array($p)) {
                    $p['version'] = $newVersion;
                } else {
                    $p = ['slug' => $p, 'version' => $newVersion];
                }
                $plugins['public'][$key] = $p;
                $updated = true;
                break;
            }
            $index++;
        }
        
        if (!$updated) {
            foreach ($plugins['premium'] as $key => $p) {
                if ($index === $num) {
                    $p['version'] = $newVersion;
                    $plugins['premium'][$key] = $p;
                    $updated = true;
                    break;
                }
                $index++;
            }
        }
        
        $profile['plugins'] = $plugins;
        
        if ($updated) {
            $output->writeln('<info>✓ Versión actualizada</info>');
        }
    }
    
    /**
     * Marca/desmarca plugin como MU-Plugin
     */
    private function toggleMUPlugin(Profile|array &$profile, int $num): void
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
    private function deletePluginByNumber(Profile|array &$profile, int $num, OutputInterface $output): void
    {
        $index = 1;
        
        foreach ($profile['plugins']['public'] as $key => $plugin) {
            if ($index === $num) {
                $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
                $plugins = $profile['plugins'];
                unset($plugins['public'][$key]);
                $plugins['public'] = array_values($plugins['public']);
                $profile['plugins'] = $plugins;
                $output->writeln("<info>✓ Plugin '{$slug}' eliminado</info>");
                return;
            }
            $index++;
        }
        
        foreach ($profile['plugins']['premium'] as $key => $plugin) {
            if ($index === $num) {
                $plugins = $profile['plugins'];
                unset($plugins['premium'][$key]);
                $plugins['premium'] = array_values($plugins['premium']);
                $profile['plugins'] = $plugins;
                $output->writeln("<info>✓ Plugin '{$plugin['name']}' eliminado</info>");
                return;
            }
            $index++;
        }
        
        foreach ($profile['plugins']['custom'] as $key => $slug) {
            if ($index === $num) {
                $plugins = $profile['plugins'];
                unset($plugins['custom'][$key]);
                $plugins['custom'] = array_values($plugins['custom']);
                $profile['plugins'] = $plugins;
                $output->writeln("<info>✓ Plugin '{$slug}' eliminado</info>");
                return;
            }
            $index++;
        }
    }
    
    /**
     * Valida que no exista duplicado del plugin en otras secciones
     * Retorna array con ['valid' => bool, 'message' => string, 'section' => string]
     */
    protected function validateNoDuplicatePlugin(Profile|array $profile, string $pluginName, string $targetSection): array
    {
        $found = [];
        
        // Buscar en public
        foreach ($profile['plugins']['public'] ?? [] as $plugin) {
            $slug = is_array($plugin) ? $plugin['slug'] : $plugin;
            if ($slug === $pluginName && $targetSection !== 'public') {
                $found[] = 'public';
            }
        }
        
        // Buscar en premium
        foreach ($profile['plugins']['premium'] ?? [] as $plugin) {
            if ($plugin['name'] === $pluginName && $targetSection !== 'premium') {
                $found[] = 'premium';
            }
        }
        
        // Buscar en custom
        foreach ($profile['plugins']['custom'] ?? [] as $slug) {
            if ($slug === $pluginName && $targetSection !== 'custom') {
                $found[] = 'custom';
            }
        }
        
        if (!empty($found)) {
            $sections = implode(', ', $found);
            return [
                'valid' => false,
                'message' => "⚠️  El plugin '{$pluginName}' ya existe en: {$sections}",
                'sections' => $found
            ];
        }
        
        return ['valid' => true, 'message' => '', 'sections' => []];
    }
    
    /**
     * Selector interactivo de plugins custom
     */
    protected function selectCustomPluginsInteractive(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): int
    {
        $pathQuestion = new Question('<fg=yellow>Path a carpeta o archivo .zip de plugins custom:</> ');
        $path = $helper->ask($input, $output, $pathQuestion);
        
        if (empty($path)) {
            return 0;
        }
        
        // Validar que sea directorio o archivo .zip
        if (!is_dir($path) && !(is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip')) {
            $output->writeln('<error>Debe ser un directorio válido o un archivo .zip</error>');
            return 0;
        }
        
        // Escanear plugins
        $detected = $this->getProfileService()->scanCustomPlugins($path);
        
        if (empty($detected)) {
            $output->writeln('<error>No se encontraron plugins válidos</error>');
            return 0;
        }
        
        // Mostrar lista
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   🔧 PLUGINS CUSTOM DETECTADOS    <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $pluginsList = [];
        $index = 1;
        foreach ($detected as $slug => $info) {
            $output->writeln("  <fg=cyan>[{$index}]</> {$info['name']} <comment>({$slug})</comment>");
            $pluginsList[$index] = $slug;
            $index++;
        }
        
        $output->writeln('');
        $selectQuestion = new Question('<fg=yellow>Seleccionar números (ej: 1,3,5) o "all" para todos:</> ');
        $selection = trim($helper->ask($input, $output, $selectQuestion));
        
        if (empty($selection)) {
            return 0;
        }
        
        // Procesar selección
        $selected = [];
        if (strtolower($selection) === 'all') {
            $selected = array_values($pluginsList);
        } else {
            $numbers = array_map('trim', explode(',', $selection));
            foreach ($numbers as $num) {
                $num = (int)$num;
                if (isset($pluginsList[$num])) {
                    $selected[] = $pluginsList[$num];
                }
            }
        }
        
        // Agregar con validación de duplicados
        $added = 0;
        foreach ($selected as $slug) {
            // Validar duplicados en otras secciones
            $validation = $this->validateNoDuplicatePlugin($profile, $slug, 'custom');
            if (!$validation['valid']) {
                $output->writeln("<error>{$validation['message']}</error>");
                continue;
            }
            
            // Validar duplicados en misma sección
            if (in_array($slug, $profile['plugins']['custom'] ?? [])) {
                $output->writeln("<error>⚠️  El plugin '{$slug}' ya existe en custom</error>");
                continue;
            }
            
            $profile['plugins']['custom'][] = $slug;
            $output->writeln("<info>✓ {$slug}</info>");
            $added++;
        }
        
        return $added;
    }
    
    /**
     * Agrega nuevo plugin (debe implementarse en el comando que use el trait)
     */
    abstract protected function addNewPlugin(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void;
    
    /**
     * ProfileService getter (debe implementarse en el comando)
     */
    abstract protected function getProfileService();
}
