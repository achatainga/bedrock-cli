<?php

namespace Roots\BedrockCli\Traits;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

trait ThemeManagementTrait
{
    protected function manageThemesInteractive(array &$profile, InputInterface $input, OutputInterface $output, $helper): void
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
    
    private function displayThemesList(array $profile, OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   🎨 THEMES                        <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $publicThemes = $profile['themes']['public'] ?? [];
        $premiumThemes = $profile['themes']['premium'] ?? [];
        
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
        
        if ($index === 1) {
            $output->writeln('<comment>(ningún theme configurado)</comment>');
            $output->writeln('');
        }
        
        $output->writeln('  <fg=cyan>[A]</> ➕ Agregar nuevo');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
    }
    
    private function editThemeByNumber(array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
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
        
        $output->writeln("  <fg=cyan>[1]</> Cambiar versión (actual: {$theme['version']})");
        $output->writeln('  <fg=cyan>[2]</> 🗑️  Eliminar este theme');
        $output->writeln('  <fg=cyan>[0]</> ⬅️  Volver');
        $output->writeln('');
        
        $question = new Question('> ');
        $choice = trim($helper->ask($input, $output, $question));
        
        if ($choice === '1') {
            $this->changeThemeVersion($profile, $num, $input, $output, $helper);
        } elseif ($choice === '2') {
            $this->deleteThemeByNumber($profile, $num, $output);
        }
    }
    
    private function getThemeByNumber(array $profile, int $num): ?array
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
        
        return null;
    }
    
    private function changeThemeVersion(array &$profile, int $num, InputInterface $input, OutputInterface $output, $helper): void
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
    
    private function deleteThemeByNumber(array &$profile, int $num, OutputInterface $output): void
    {
        $index = 1;
        
        foreach ($profile['themes']['public'] as $key => $theme) {
            if ($index === $num) {
                $slug = is_array($theme) ? $theme['slug'] : $theme;
                unset($profile['themes']['public'][$key]);
                $profile['themes']['public'] = array_values($profile['themes']['public']);
                $output->writeln("<info>✓ Theme '{$slug}' eliminado</info>");
                return;
            }
            $index++;
        }
        
        foreach ($profile['themes']['premium'] as $key => $theme) {
            if ($index === $num) {
                unset($profile['themes']['premium'][$key]);
                $profile['themes']['premium'] = array_values($profile['themes']['premium']);
                $output->writeln("<info>✓ Theme '{$theme['name']}' eliminado</info>");
                return;
            }
            $index++;
        }
    }
    
    abstract protected function addNewTheme(array &$profile, InputInterface $input, OutputInterface $output, $helper): void;
}
