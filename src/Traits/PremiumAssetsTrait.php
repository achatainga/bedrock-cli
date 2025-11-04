<?php

namespace Roots\BedrockCli\Traits;

use Roots\BedrockCli\Services\PremiumRepoService;
use Roots\BedrockCli\Services\AuthService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

trait PremiumAssetsTrait
{
    private function selectPremiumPlugins(InputInterface $input, OutputInterface $output, $helper): array
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   💎 PLUGINS PREMIUM               <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $sourceQuestion = new ChoiceQuestion(
            '<fg=yellow>Fuente de plugins premium:</> ',
            [
                '1' => 'Repositorio privado (GitLab/GitHub/Bitbucket)',
                '2' => 'Carpeta local',
                '3' => 'Archivo ZIP',
                '0' => 'Omitir'
            ],
            '0'
        );
        
        $source = $helper->ask($input, $output, $sourceQuestion);
        
        if ($source === '0' || $source === 'Omitir') {
            return [];
        }
        
        return match($source) {
            '1', 'Repositorio privado (GitLab/GitHub/Bitbucket)' => $this->selectFromRepository($input, $output, $helper),
            '2', 'Carpeta local' => $this->selectFromLocalPath($input, $output, $helper),
            '3', 'Archivo ZIP' => $this->selectFromZip($input, $output, $helper),
            default => []
        };
    }

    private function selectFromRepository(InputInterface $input, OutputInterface $output, $helper): array
    {
        $output->writeln('');
        $output->writeln('<info>📦 Repositorio Privado</info>');
        $output->writeln('');
        
        $urlQuestion = new Question('<fg=yellow>URL del repositorio:</> ');
        $repoUrl = $helper->ask($input, $output, $urlQuestion);
        
        if (empty($repoUrl)) {
            return [];
        }
        
        // Verificar acceso
        $service = new PremiumRepoService();
        $access = $service->checkAccess($repoUrl);
        
        if (!$access['success']) {
            $output->writeln("<error>❌ {$access['message']}</error>");
            
            if ($access['needs_auth']) {
                $output->writeln('');
                $output->writeln('<comment>Configura las credenciales con:</comment>');
                $output->writeln('  bedrock menu → [T] Auth → [1] Agregar');
                $output->writeln('');
            }
            
            return [];
        }
        
        $output->writeln('<info>✓ Acceso verificado</info>');
        $output->writeln('');
        
        // Escanear plugins
        $output->writeln('<comment>Escaneando repositorio...</comment>');
        
        try {
            $plugins = $service->scanRepository($repoUrl, 'packages');
            
            if (empty($plugins)) {
                $output->writeln('<error>No se encontraron plugins en packages/</error>');
                return [];
            }
            
            $output->writeln('');
            $output->writeln("<info>Plugins encontrados: " . count($plugins) . "</info>");
            $output->writeln('');
            
            // Mostrar lista
            $pluginsList = [];
            $index = 1;
            foreach ($plugins as $plugin) {
                $output->writeln("  <fg=cyan>[{$index}]</> {$plugin['name']} - v{$plugin['latest']}");
                $pluginsList[$index] = $plugin;
                $index++;
            }
            
            $output->writeln('');
            $selectQuestion = new Question('<fg=yellow>Seleccionar números (ej: 1,3,5) o Enter para omitir:</> ');
            $selection = $helper->ask($input, $output, $selectQuestion);
            
            if (empty($selection)) {
                return [];
            }
            
            $selected = array_map('trim', explode(',', $selection));
            $selectedPlugins = [];
            
            foreach ($selected as $num) {
                $num = (int)$num;
                if (isset($pluginsList[$num])) {
                    $plugin = $pluginsList[$num];
                    
                    // Seleccionar versión
                    $version = $this->selectVersion($plugin, $helper, $input, $output);
                    
                    $selectedPlugins[] = [
                        'name' => $plugin['slug'],
                        'version' => $version,
                        'source' => 'vcs',
                        'type' => 'git',
                        'url' => $repoUrl,
                        'path' => "packages/{$plugin['slug']}/{$version}/"
                    ];
                    
                    $output->writeln("<info>✓ {$plugin['slug']}:{$version}</info>");
                }
            }
            
            return $selectedPlugins;
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return [];
        }
    }

    private function selectFromLocalPath(InputInterface $input, OutputInterface $output, $helper): array
    {
        $output->writeln('');
        $output->writeln('<info>📁 Carpeta Local</info>');
        $output->writeln('');
        
        $pathQuestion = new Question('<fg=yellow>Path absoluto o relativo:</> ');
        $path = $helper->ask($input, $output, $pathQuestion);
        
        if (empty($path) || !is_dir($path)) {
            $output->writeln('<error>Directorio no válido</error>');
            return [];
        }
        
        // Escanear directorio
        $plugins = [];
        $dirs = array_filter(glob($path . '/*'), 'is_dir');
        
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            $composerFile = $dir . '/composer.json';
            
            if (file_exists($composerFile)) {
                $composer = json_decode(file_get_contents($composerFile), true);
                $version = $composer['version'] ?? '1.0.0';
                
                $plugins[] = [
                    'name' => $slug,
                    'version' => $version,
                    'source' => 'path',
                    'path' => $dir
                ];
            }
        }
        
        if (empty($plugins)) {
            $output->writeln('<error>No se encontraron plugins válidos</error>');
            return [];
        }
        
        $output->writeln("<info>Plugins encontrados: " . count($plugins) . "</info>");
        foreach ($plugins as $plugin) {
            $output->writeln("  ✓ {$plugin['name']} - v{$plugin['version']}");
        }
        
        return $plugins;
    }

    private function selectFromZip(InputInterface $input, OutputInterface $output, $helper): array
    {
        $output->writeln('');
        $output->writeln('<info>📦 Archivo ZIP</info>');
        $output->writeln('');
        
        $zipQuestion = new Question('<fg=yellow>Path al archivo ZIP:</> ');
        $zipPath = $helper->ask($input, $output, $zipQuestion);
        
        if (empty($zipPath) || !file_exists($zipPath)) {
            $output->writeln('<error>Archivo no encontrado</error>');
            return [];
        }
        
        // Extraer metadata del ZIP
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $output->writeln('<error>No se pudo abrir el ZIP</error>');
            return [];
        }
        
        // Buscar composer.json
        $name = basename($zipPath, '.zip');
        $version = '1.0.0';
        
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (str_ends_with($filename, 'composer.json')) {
                $content = $zip->getFromIndex($i);
                $composer = json_decode($content, true);
                $version = $composer['version'] ?? $version;
                break;
            }
        }
        
        $zip->close();
        
        return [[
            'name' => $name,
            'version' => $version,
            'source' => 'zip',
            'path' => $zipPath
        ]];
    }

    private function selectVersion(array $plugin, $helper, InputInterface $input, OutputInterface $output): string
    {
        if (empty($plugin['versions']) || count($plugin['versions']) === 1) {
            return $plugin['latest'];
        }
        
        $versionQuestion = new ChoiceQuestion(
            "<fg=yellow>Versión de {$plugin['slug']}:</> ",
            array_slice($plugin['versions'], 0, 10),
            0
        );
        
        return $helper->ask($input, $output, $versionQuestion);
    }

    private function selectPremiumTheme(InputInterface $input, OutputInterface $output, $helper): ?array
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   🎨 TEMA PREMIUM                  <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        $sourceQuestion = new ChoiceQuestion(
            '<fg=yellow>Fuente del tema:</> ',
            [
                '1' => 'Repositorio privado',
                '2' => 'Carpeta local',
                '3' => 'Archivo ZIP',
                '0' => 'Omitir'
            ],
            '0'
        );
        
        $source = $helper->ask($input, $output, $sourceQuestion);
        
        if ($source === '0' || $source === 'Omitir') {
            return null;
        }
        
        // Reutilizar misma lógica que plugins
        return null;
    }
}
