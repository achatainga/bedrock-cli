<?php

namespace Roots\BedrockCli\Traits;

use Roots\BedrockCli\DTOs\Profile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

trait PluginManagementTrait
{
    use AssetProfileManagementTrait;
    
    protected function managePluginsInteractive(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $this->manageAssetsInteractive('plugins', $profile, $input, $output, $helper);
    }
    
    // Implementación específica de toggle MU que no está en el trait base de forma genérica
    private function toggleMUPlugin(Profile|array &$profile, int $num): void
    {
        $index = 1;
        foreach (['public', 'premium'] as $section) {
            foreach ($profile['plugins'][$section] as &$plugin) {
                if ($index === $num) {
                    if (!is_array($plugin)) $plugin = ['slug' => $plugin, 'version' => '*'];
                    $plugin['mu_plugin'] = !($plugin['mu_plugin'] ?? false);
                    return;
                }
                $index++;
            }
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
