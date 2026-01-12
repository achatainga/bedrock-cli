<?php

namespace Roots\BedrockCli\Traits;

use Roots\BedrockCli\DTOs\Profile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;

trait AssetProfileManagementTrait
{
    /**
     * Muestra menú CRUD interactivo para gestión de assets (plugins/themes) en un Profile
     */
    protected function manageAssetsInteractive(string $type, Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $label = ($type === 'plugins') ? '📦 PLUGINS' : '🎨 THEMES';
        
        while (true) {
            $this->displayAssetsList($type, $profile, $output, $label);
            
            $question = new Question('> ');
            $action = strtoupper(trim($helper->ask($input, $output, $question)));
            
            if ($action === '0') {
                break;
            }
            
            if ($action === 'A') {
                $method = ($type === 'plugins') ? 'addNewPlugin' : 'addNewTheme';
                $this->$method($profile, $input, $output, $helper);
            } elseif (is_numeric($action)) {
                $num = (int)$action;
                $this->editAssetByNumber($type, $profile, $num, $input, $output, $helper);
            }
            
            $output->writeln('');
        }
    }

    /**
     * Muestra lista numerada de assets
     */
    private function displayAssetsList(string $type, Profile|array $profile, OutputInterface $output, string $label): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln("<fg=cyan>║</>   " . str_pad($label, 30) . " <fg=cyan>║</>");
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $public = $profile[$type]['public'] ?? [];
        $premium = $profile[$type]['premium'] ?? [];
        $custom = $profile[$type]['custom'] ?? [];
        
        $index = 1;
        $sections = [
            '🌐 PÚBLICOS' => $public,
            '💎 PREMIUM' => $premium,
            '🔧 CUSTOM' => $custom
        ];

        foreach ($sections as $sectionLabel => $items) {
            if (empty($items)) continue;
            
            $color = str_contains($sectionLabel, 'PÚBLICOS') ? 'green' : (str_contains($sectionLabel, 'PREMIUM') ? 'magenta' : 'yellow');
            $output->writeln("<fg={$color}>{$sectionLabel} (" . count($items) . ")</>");
            
            foreach ($items as $item) {
                $display = $this->formatAssetDisplay($type, $item);
                $muBadge = ($type === 'plugins' && is_array($item) && ($item['mu_plugin'] ?? false)) ? ' <fg=yellow>[MU]</>' : '';
                $output->writeln("  <fg=cyan>[{$index}]</> {$display}{$muBadge}");
                $index++;
            }
            $output->writeln('');
        }
        
        if ($index === 1) {
            $output->writeln('<comment>(ninguno configurado)</comment>');
            $output->writeln('');
        }
        
        $output->writeln('  <fg=cyan>[A]</> ➕ Agregar nuevo');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
    }

    private function formatAssetDisplay(string $type, $item): string
    {
        if (is_array($item)) {
            $name = $item['slug'] ?? $item['name'] ?? 'unknown';
            $version = $item['version'] ?? '*';
            return "{$name}:{$version}";
        }
        return "{$item}:*";
    }

    /**
     * Edita un asset específico
     */
    private function editAssetByNumber(string $type, Profile|array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
    {
        $asset = $this->getAssetByNumber($type, $profile, $num);
        if (!$asset) {
            $output->writeln('<error>No encontrado</error>');
            return;
        }
        
        $output->writeln("\n<fg=cyan>╔═══════════════════════════════════════╗</>");
        $output->writeln("<fg=cyan>║</>   ✏️  " . str_pad($asset['display'], 30) . " <fg=cyan>║</>");
        $output->writeln("<fg=cyan>╚═══════════════════════════════════════╝</>\n");
        
        $options = [
            '1' => "Cambiar versión (actual: {$asset['version']})"
        ];
        
        if ($type === 'plugins' && $asset['section'] !== 'custom') {
            $muLabel = ($asset['mu_plugin'] ?? false) ? 'Desmarcar' : 'Marcar';
            $options['2'] = "🔒 {$muLabel} como MU-Plugin";
        }
        
        $options['3'] = "🗑️  Eliminar este " . rtrim($type, 's');
        $options['0'] = "⬅️  Volver";

        foreach ($options as $key => $text) {
            $output->writeln("  <fg=cyan>[{$key}]</> {$text}");
        }

        $choice = trim($helper->ask($input, $output, new Question('> ')));
        
        if ($choice === '1') {
            $this->changeAssetVersion($type, $profile, $num, $input, $output, $helper);
        } elseif ($choice === '2' && isset($options['2'])) {
            $this->toggleMUPlugin($profile, $num); // Special case for plugins
            $output->writeln("<info>✓ Estado MU actualizado</info>");
        } elseif ($choice === '3') {
            $this->deleteAssetByNumber($type, $profile, $num, $output);
        }
    }

    private function getAssetByNumber(string $type, Profile|array $profile, int $num): ?array
    {
        $index = 1;
        $sections = ['public', 'premium', 'custom'];
        
        foreach ($sections as $section) {
            foreach ($profile[$type][$section] ?? [] as $item) {
                if ($index === $num) {
                    $slug = is_array($item) ? ($item['slug'] ?? $item['name']) : $item;
                    $version = is_array($item) ? ($item['version'] ?? '*') : '*';
                    return [
                        'section' => $section,
                        'slug' => $slug,
                        'version' => $version,
                        'mu_plugin' => is_array($item) ? ($item['mu_plugin'] ?? false) : false,
                        'display' => "{$slug}:{$version}"
                    ];
                }
                $index++;
            }
        }
        return null;
    }

    private function changeAssetVersion(string $type, Profile|array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
    {
        $asset = $this->getAssetByNumber($type, $profile, $num);
        $newVersion = $helper->ask($input, $output, new Question("Nueva versión [{$asset['version']}]: ", $asset['version']));
        
        $index = 1;
        foreach (['public', 'premium'] as $section) {
            foreach ($profile[$type][$section] ?? [] as $key => $item) {
                if ($index === $num) {
                    if (is_array($item)) {
                        $profile[$type][$section][$key]['version'] = $newVersion;
                    } else {
                        $profile[$type][$section][$key] = ['slug' => $item, 'version' => $newVersion];
                    }
                    $output->writeln("<info>✓ Versión actualizada</info>");
                    return;
                }
                $index++;
            }
        }
    }

    protected function deleteAssetByNumber(string $type, Profile|array &$profile, int $num, OutputInterface $output): void
    {
        $index = 1;
        foreach (['public', 'premium', 'custom'] as $section) {
            foreach ($profile[$type][$section] ?? [] as $key => $item) {
                if ($index === $num) {
                    $name = is_array($item) ? ($item['slug'] ?? $item['name']) : $item;
                    unset($profile[$type][$section][$key]);
                    $profile[$type][$section] = array_values($profile[$type][$section]);
                    $output->writeln("<info>✓ '{$name}' eliminado</info>");
                    return;
                }
                $index++;
            }
        }
    }
}
