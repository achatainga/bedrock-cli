<?php

namespace Roots\BedrockCli\Traits;

use Roots\BedrockCli\DTOs\Profile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

trait ThemeManagementTrait
{
    protected function manageThemesInteractive(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        while (true) {
            $this->displayThemesList($profile, $output);
            
            $question = new Question('> ');
            $action = strtoupper(trim($helper->ask($input, $output, $question)));
            
            if ($action === '0') {
                break;
            }
            
            if ($action === 'A') {
                $this->addNewTheme($profile, $input, $output, $helper);
            } elseif (is_numeric($action)) {
                $num = (int)$action;
                $this->editThemeByNumber($profile, $num, $input, $output, $helper);
            }
            
            $output->writeln('');
        }
    }
    
    private function displayThemesList(Profile|array $profile, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   🎨 THEMES                        <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $publicThemes = $profile['themes']['public'] ?? [];
        $premiumThemes = $profile['themes']['premium'] ?? [];
        $customThemes = $profile['themes']['custom'] ?? [];
        
        $index = 1;
        
        if (!empty($publicThemes)) {
            $output->writeln('<fg=green>🌐 PÚBLICOS (' . count($publicThemes) . ')</>');
            foreach ($publicThemes as $theme) {
                $slug = is_array($theme) ? $theme['slug'] : $theme;
                $version = is_array($theme) ? ($theme['version'] ?? '*') : '*';
                $output->writeln("  <fg=cyan>[{$index}]</> {$slug}:{$version}");
                $index++;
            }
            $output->writeln('');
        }
        
        if (!empty($premiumThemes)) {
            $output->writeln('<fg=magenta>💎 PREMIUM (' . count($premiumThemes) . ')</>');
            foreach ($premiumThemes as $theme) {
                $output->writeln("  <fg=cyan>[{$index}]</> {$theme['name']}:{$theme['version']}");
                $index++;
            }
            $output->writeln('');
        }
        
        if (!empty($customThemes)) {
            $output->writeln('<fg=yellow>🔧 CUSTOM (' . count($customThemes) . ')</>');
            foreach ($customThemes as $slug) {
                $output->writeln("  <fg=cyan>[{$index}]</> {$slug}");
                $index++;
            }
            $output->writeln('');
        }
        
        if ($index === 1) {
            $output->writeln('<comment>(ningún theme configurado)</comment>');
            $output->writeln('');
        }
        
        $output->writeln('  <fg=cyan>[A]</> ➕ Agregar nuevo');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
    }
    
    private function editThemeByNumber(Profile|array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
    {
        $theme = $this->getThemeByNumber($profile, $num);
        
        if (!$theme) {
            $output->writeln('<error>Theme no encontrado</error>');
            return;
        }
        
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln("<fg=cyan>║</>   ✏️  {$theme['display']}");
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        if ($theme['type'] !== 'custom') {
            $output->writeln("  <fg=cyan>[1]</> Cambiar versión (actual: {$theme['version']})");
        }
        
        $output->writeln('  <fg=cyan>[2]</> 🗑️  Eliminar este theme');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
        
        $question = new Question('> ');
        $choice = trim($helper->ask($input, $output, $question));
        
        if ($choice === '1' && $theme['type'] !== 'custom') {
            $this->changeThemeVersion($profile, $num, $input, $output, $helper);
        } elseif ($choice === '2') {
            $this->deleteThemeByNumber($profile, $num, $output);
        }
    }
    
    private function getThemeByNumber(Profile|array $profile, int $num): ?array
    {
        $index = 1;
        
        foreach ($profile['themes']['public'] ?? [] as $theme) {
            if ($index === $num) {
                $slug = is_array($theme) ? $theme['slug'] : $theme;
                $version = is_array($theme) ? ($theme['version'] ?? '*') : '*';
                return [
                    'type' => 'public',
                    'slug' => $slug,
                    'version' => $version,
                    'display' => "{$slug}:{$version}"
                ];
            }
            $index++;
        }
        
        foreach ($profile['themes']['premium'] ?? [] as $theme) {
            if ($index === $num) {
                return [
                    'type' => 'premium',
                    'slug' => $theme['name'],
                    'version' => $theme['version'],
                    'display' => "{$theme['name']}:{$theme['version']}"
                ];
            }
            $index++;
        }
        
        foreach ($profile['themes']['custom'] ?? [] as $slug) {
            if ($index === $num) {
                return [
                    'type' => 'custom',
                    'slug' => $slug,
                    'version' => '*',
                    'display' => $slug
                ];
            }
            $index++;
        }
        
        return null;
    }
    
    private function changeThemeVersion(Profile|array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
    {
        $theme = $this->getThemeByNumber($profile, $num);
        $question = new Question("Nueva versión [{$theme['version']}]: ", $theme['version']);
        $newVersion = $helper->ask($input, $output, $question);
        
        $index = 1;
        
        foreach ($profile['themes']['public'] as &$t) {
            if ($index === $num) {
                if (is_array($t)) {
                    $t['version'] = $newVersion;
                } else {
                    $t = ['slug' => $t, 'version' => $newVersion];
                }
                $output->writeln('<info>✓ Versión actualizada</info>');
                return;
            }
            $index++;
        }
        
        foreach ($profile['themes']['premium'] as &$t) {
            if ($index === $num) {
                $t['version'] = $newVersion;
                $output->writeln('<info>✓ Versión actualizada</info>');
                return;
            }
            $index++;
        }
    }
    
    private function deleteThemeByNumber(Profile|array &$profile, int $num, OutputInterface $output): void
    {
        $index = 1;
        
        foreach ($profile['themes']['public'] ?? [] as $key => $theme) {
            if ($index === $num) {
                $slug = is_array($theme) ? $theme['slug'] : $theme;
                unset($profile['themes']['public'][$key]);
                $profile['themes']['public'] = array_values($profile['themes']['public']);
                $output->writeln("<info>✓ Theme '{$slug}' eliminado</info>");
                return;
            }
            $index++;
        }
        
        foreach ($profile['themes']['premium'] ?? [] as $key => $theme) {
            if ($index === $num) {
                $themes = $profile['themes'];
                unset($themes['premium'][$key]);
                $themes['premium'] = array_values($themes['premium']);
                $profile['themes'] = $themes;
                $output->writeln("<info>✓ Theme '{$theme['name']}' eliminado</info>");
                return;
            }
            $index++;
        }
        
        foreach ($profile['themes']['custom'] as $key => $slug) {
            if ($index === $num) {
                unset($profile['themes']['custom'][$key]);
                $profile['themes']['custom'] = array_values($profile['themes']['custom']);
                $output->writeln("<info>✓ Theme '{$slug}' eliminado</info>");
                return;
            }
            $index++;
        }
    }
    
    protected function selectCustomThemesInteractive(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): int
    {
        $pathQuestion = new Question('<fg=yellow>Path a carpeta de themes custom:</> ');
        $path = $helper->ask($input, $output, $pathQuestion);
        
        if (empty($path)) {
            return 0;
        }
        
        // Validar que sea directorio
        if (!is_dir($path)) {
            $output->writeln('<error>Debe ser un directorio válido</error>');
            return 0;
        }
        
        $detected = $this->getProfileService()->scanCustomThemes($path);
        
        if (empty($detected)) {
            $output->writeln('<error>No se encontraron themes válidos</error>');
            return 0;
        }
        
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   🎨 THEMES CUSTOM DETECTADOS     <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $themesList = [];
        $index = 1;
        foreach ($detected as $slug => $info) {
            $output->writeln("  <fg=cyan>[{$index}]</> {$info['name']} <comment>({$slug})</comment>");
            $themesList[$index] = $slug;
            $index++;
        }
        
        $output->writeln('');
        $selectQuestion = new Question('<fg=yellow>Seleccionar números (ej: 1,3,5) o "all" para todos:</> ');
        $selection = trim($helper->ask($input, $output, $selectQuestion));
        
        if (empty($selection)) {
            return 0;
        }
        
        $selected = [];
        if (strtolower($selection) === 'all') {
            $selected = array_values($themesList);
        } else {
            $numbers = array_map('trim', explode(',', $selection));
            foreach ($numbers as $num) {
                $num = (int)$num;
                if (isset($themesList[$num])) {
                    $selected[] = $themesList[$num];
                }
            }
        }
        
        $added = 0;
        foreach ($selected as $slug) {
            if (in_array($slug, $profile['themes']['custom'] ?? [])) {
                $output->writeln("<error>⚠️  El theme '{$slug}' ya existe en custom</error>");
                continue;
            }
            
            $profile['themes']['custom'][] = $slug;
            $output->writeln("<info>✓ {$slug}</info>");
            $added++;
        }
        
        return $added;
    }
    
    abstract protected function addNewTheme(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void;
    abstract protected function getProfileService();
}
