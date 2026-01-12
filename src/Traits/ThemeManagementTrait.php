<?php

namespace Roots\BedrockCli\Traits;

use Roots\BedrockCli\DTOs\Profile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

trait ThemeManagementTrait
{
    use AssetProfileManagementTrait;
    
    protected function manageThemesInteractive(Profile|array &$profile, InputInterface $input, OutputInterface $output, $helper): void
    {
        $this->manageAssetsInteractive('themes', $profile, $input, $output, $helper);
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
