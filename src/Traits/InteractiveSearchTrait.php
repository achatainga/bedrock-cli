<?php

namespace Roots\BedrockCli\Traits;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Roots\BedrockCli\Services\WordPressApiService;

trait InteractiveSearchTrait
{
    protected function displayPluginsTable(array $plugins, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(['#', 'Nombre', 'Slug', 'Instalaciones']);
        $table->setColumnMaxWidth(1, 35);
        
        $colors = ['cyan', 'green', 'yellow', 'blue', 'magenta', 'red', 'white'];
        
        foreach ($plugins as $index => $plugin) {
            $colorIndex = $index % count($colors);
            $color = $colors[$colorIndex];
            
            $table->addRow([
                "<fg={$color}>" . ($index + 1) . "</>",
                $plugin['name'],
                "<fg={$color}>" . $plugin['slug'] . "</>",
                number_format($plugin['active_installs'] ?? 0)
            ]);
        }
        
        $table->render();
    }

    protected function displayThemesTable(array $themes, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(['#', 'Nombre', 'Slug', 'Rating']);
        $table->setColumnMaxWidth(1, 35);
        
        $colors = ['cyan', 'green', 'yellow', 'blue', 'magenta', 'red', 'white'];
        
        foreach ($themes as $index => $theme) {
            $colorIndex = $index % count($colors);
            $color = $colors[$colorIndex];
            
            $table->addRow([
                "<fg={$color}>" . ($index + 1) . "</>",
                $theme['name'],
                "<fg={$color}>" . $theme['slug'] . "</>",
                ($theme['rating'] ?? 0) . '%'
            ]);
        }
        
        $table->render();
    }

    protected function selectPluginVersion(string $slug, $helper, $input, OutputInterface $output): string
    {
        $apiService = new WordPressApiService();
        $pluginInfo = $apiService->getPluginInfo($slug);
        
        if (!$pluginInfo || !isset($pluginInfo['versions'])) {
            return '*';
        }
        
        $versions = array_keys($pluginInfo['versions']);
        $versions = array_filter($versions, function($v) {
            return $v !== 'trunk' && preg_match('/^\d+\.\d+/', $v);
        });
        usort($versions, function($a, $b) {
            return version_compare($b, $a);
        });
        
        $latestVersion = $versions[0] ?? '*';
        $output->writeln("\n<comment>Plugin: {$slug}</comment>");
        $output->writeln("<comment>Última versión: {$latestVersion}</comment>");
        $output->writeln("<comment>Versiones disponibles (mostrando de 10 en 10):</comment>\n");
        
        return $this->selectVersionFromList($versions, $helper, $input, $output);
    }

    protected function selectThemeVersion(string $slug, $helper, $input, OutputInterface $output): string
    {
        $apiService = new WordPressApiService();
        $themeInfo = $apiService->getThemeInfo($slug);
        
        if (!$themeInfo) {
            return '*';
        }
        
        $latestVersion = $themeInfo['version'] ?? '*';
        $output->writeln("\n<comment>Tema: {$slug}</comment>");
        $output->writeln("<comment>Última versión: {$latestVersion}</comment>");
        
        if (isset($themeInfo['versions']) && is_array($themeInfo['versions'])) {
            $versions = array_keys($themeInfo['versions']);
            $versions = array_filter($versions, function($v) {
                return preg_match('/^\d+\.\d+/', $v);
            });
            usort($versions, function($a, $b) {
                return version_compare($b, $a);
            });
            
            if (!empty($versions)) {
                $output->writeln("<comment>Versiones disponibles (mostrando de 10 en 10):</comment>\n");
                return $this->selectVersionFromList($versions, $helper, $input, $output);
            }
        }
        
        $output->writeln("<comment>Solo versión actual disponible</comment>");
        $output->writeln("\n  <fg=cyan>[1]</> {$latestVersion}");
        $output->writeln("  <fg=cyan>[0]</> * (siempre la última)\n");
        
        $versionQuestion = new Question("Seleccionar versión [0]: ", '0');
        $choice = $helper->ask($input, $output, $versionQuestion);
        
        return $choice === '1' ? $latestVersion : '*';
    }

    private function selectVersionFromList(array $versions, $helper, $input, OutputInterface $output): string
    {
        $page = 0;
        $perPage = 10;
        $totalVersions = count($versions);
        
        while (true) {
            $start = $page * $perPage;
            $pageVersions = array_slice($versions, $start, $perPage);
            
            if (empty($pageVersions)) {
                break;
            }
            
            $cols = 4;
            $rows = ceil(count($pageVersions) / $cols);
            
            for ($row = 0; $row < $rows; $row++) {
                $line = '';
                for ($col = 0; $col < $cols; $col++) {
                    $idx = $row + ($col * $rows);
                    if (isset($pageVersions[$idx])) {
                        $num = $start + $idx + 1;
                        $ver = $pageVersions[$idx];
                        $line .= sprintf("<fg=cyan>[%2d]</> %-15s ", $num, $ver);
                    }
                }
                $output->writeln($line);
            }
            
            $output->writeln("\n  <fg=cyan>[0]</> * (siempre la última)");
            $output->writeln("  <fg=yellow>[N]</> Ver más versiones");
            
            $versionQuestion = new Question("\nSeleccionar versión [1]: ", '1');
            $versionChoice = strtoupper($helper->ask($input, $output, $versionQuestion));
            
            if ($versionChoice === 'N' && ($start + $perPage) < $totalVersions) {
                $page++;
                $output->writeln("");
                continue;
            }
            
            if ($versionChoice === '0') {
                return '*';
            } elseif (is_numeric($versionChoice) && isset($versions[$versionChoice - 1])) {
                return $versions[$versionChoice - 1];
            } else {
                return $versions[0] ?? '*';
            }
        }
        
        return '*';
    }
}
