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
                '1' => 'Repositorio de paquetes (packages/plugin1/, packages/plugin2/)',
                '2' => 'Repositorio individual (1 repo = 1 plugin)',
                '3' => 'Importar .zip local ahora',
                '4' => 'Desde cache (ya importado)',
                '5' => 'Carpeta local',
                '0' => 'Omitir'
            ],
            '0'
        );
        
        $source = $helper->ask($input, $output, $sourceQuestion);
        
        if ($source === '0' || $source === 'Omitir') {
            return [];
        }
        
        return match($source) {
            '1', 'Repositorio de paquetes (packages/plugin1/, packages/plugin2/)' => $this->selectFromRepository($input, $output, $helper, 'plugin'),
            '2', 'Repositorio individual (1 repo = 1 plugin)' => $this->selectFromIndividualRepo($input, $output, $helper, 'plugin'),
            '3', 'Importar .zip local ahora' => $this->importLocalZip($input, $output, $helper, 'plugin'),
            '4', 'Desde cache (ya importado)' => $this->selectFromCache($input, $output, $helper, 'plugin'),
            '5', 'Carpeta local' => $this->selectFromLocalPath($input, $output, $helper),
            default => []
        };
    }

    private function selectFromRepository(InputInterface $input, OutputInterface $output, $helper, string $type = 'plugin'): array
    {
        $output->writeln('');
        $output->writeln('<info>📦 Repositorio Privado</info>');
        $output->writeln('');
        
        $urlQuestion = new Question('<fg=yellow>URL del repositorio:</> ');
        $repoUrl = $helper->ask($input, $output, $urlQuestion);
        
        if (empty($repoUrl)) {
            return [];
        }
        
        $branchQuestion = new Question('<fg=yellow>Rama [main]:</> ', 'main');
        $branch = $helper->ask($input, $output, $branchQuestion);
        
        // Verificar acceso
        $authService = new AuthService();
        $output->writeln("<comment>[DEBUG] AuthService created, checking domain...</comment>");
        $domain = parse_url($repoUrl, PHP_URL_HOST);
        $output->writeln("<comment>[DEBUG] Domain: {$domain}</comment>");
        $hasAuth = $authService->hasAuth($domain);
        $output->writeln("<comment>[DEBUG] hasAuth result: " . ($hasAuth ? 'YES' : 'NO') . "</comment>");
        $service = new PremiumRepoService($authService, $repoUrl, $branch);
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
            $filterType = $type === 'plugin' ? 'wordpress-plugin' : 'wordpress-theme';
            $items = $service->scanRepository($repoUrl, 'packages', $branch, $filterType);
            
            if (empty($items)) {
                $typeName = $type === 'plugin' ? 'plugins' : 'themes';
                $output->writeln("<error>No se encontraron {$typeName} en packages/</error>");
                return [];
            }
            
            $output->writeln('');
            $typeName = $type === 'plugin' ? 'Plugins' : 'Themes';
            $output->writeln("<info>{$typeName} encontrados: " . count($items) . "</info>");
            $output->writeln('');
            
            // Mostrar lista
            $itemsList = [];
            $index = 1;
            foreach ($items as $item) {
                $output->writeln("  <fg=cyan>[{$index}]</> {$item['name']} - v{$item['latest']}");
                $itemsList[$index] = $item;
                $index++;
            }
            
            $output->writeln('');
            $selectQuestion = new Question('<fg=yellow>Seleccionar números (ej: 1,3,5) o Enter para omitir:</> ');
            $selection = $helper->ask($input, $output, $selectQuestion);
            
            if (empty($selection)) {
                return [];
            }
            
            $selected = array_map('trim', explode(',', $selection));
            $selectedItems = [];
            
            foreach ($selected as $num) {
                $num = (int)$num;
                if (isset($itemsList[$num])) {
                    $item = $itemsList[$num];
                    
                    // Seleccionar versión
                    $version = $this->selectVersion($item, $helper, $input, $output);
                    
                    $selectedItems[] = [
                        'name' => $item['slug'],
                        'version' => $version,
                        'source' => 'vcs',
                        'type' => 'git',
                        'url' => $repoUrl,
                        'path' => "packages/{$item['slug']}/{$version}/"
                    ];
                    
                    $output->writeln("<info>✓ {$item['slug']}:{$version}</info>");
                }
            }
            
            return $selectedItems;
            
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
        
        if (!class_exists('ZipArchive')) {
            $output->writeln('<error>Extensión ZIP no disponible en PHP</error>');
            $output->writeln('<comment>Instala php-zip: apt install php-zip (Linux) o habilita en php.ini (Windows)</comment>');
            return [];
        }
        
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

    private function selectFromIndividualRepo(InputInterface $input, OutputInterface $output, $helper, string $type = 'plugin'): array
    {
        $output->writeln('');
        $output->writeln("<info>📦 Repositorio Individual ({$type})</info>");
        $output->writeln('');
        
        $urlQuestion = new Question('<fg=yellow>URL del repositorio Git:</> ');
        $repoUrl = $helper->ask($input, $output, $urlQuestion);
        
        if (empty($repoUrl)) {
            return [];
        }
        
        // Verificar acceso
        $authService = new AuthService();
        $output->writeln("<comment>[DEBUG] AuthService created, checking domain...</comment>");
        $domain = parse_url($repoUrl, PHP_URL_HOST);
        $output->writeln("<comment>[DEBUG] Domain: {$domain}</comment>");
        $hasAuth = $authService->hasAuth($domain);
        $output->writeln("<comment>[DEBUG] hasAuth result: " . ($hasAuth ? 'YES' : 'NO') . "</comment>");
        $service = new PremiumRepoService($authService, $repoUrl, $branch);
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
        
        // Extraer nombre del repo
        $slug = $this->extractSlugFromUrl($repoUrl);
        
        $nameQuestion = new Question("<fg=yellow>Nombre del {$type} [{$slug}]:</> ", $slug);
        $name = $helper->ask($input, $output, $nameQuestion);
        
        $versionQuestion = new Question('<fg=yellow>Versión [*]:</> ', '*');
        $version = $helper->ask($input, $output, $versionQuestion);
        
        $output->writeln("<info>✓ {$name}:{$version}</info>");
        
        return [[
            'name' => $name,
            'version' => $version,
            'source' => 'vcs',
            'type' => 'git',
            'url' => $repoUrl
        ]];
    }

    private function extractSlugFromUrl(string $url): string
    {
        // https://gitlab.com/user/my-plugin.git → my-plugin
        // git@gitlab.com:user/my-plugin.git → my-plugin
        if (preg_match('#[:/]([^/]+?)(?:\.git)?$#', $url, $matches)) {
            return $matches[1];
        }
        return 'plugin';
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
                '1' => 'Repositorio de paquetes (packages/theme1/, packages/theme2/)',
                '2' => 'Repositorio individual (1 repo = 1 tema)',
                '3' => 'Importar .zip local ahora',
                '4' => 'Desde cache (ya importado)',
                '5' => 'Carpeta local',
                '0' => 'Omitir'
            ],
            '0'
        );
        
        $source = $helper->ask($input, $output, $sourceQuestion);
        
        if ($source === '0' || $source === 'Omitir') {
            return null;
        }
        
        $result = match($source) {
            '1', 'Repositorio de paquetes (packages/theme1/, packages/theme2/)' => $this->selectFromRepository($input, $output, $helper, 'theme'),
            '2', 'Repositorio individual (1 repo = 1 tema)' => $this->selectFromIndividualRepo($input, $output, $helper, 'theme'),
            '3', 'Importar .zip local ahora' => $this->importLocalZip($input, $output, $helper, 'theme'),
            '4', 'Desde cache (ya importado)' => $this->selectFromCache($input, $output, $helper, 'theme'),
            '5', 'Carpeta local' => $this->selectFromLocalPath($input, $output, $helper),
            default => []
        };
        
        return !empty($result) ? $result[0] : null;
    }

    private function importLocalZip(InputInterface $input, OutputInterface $output, $helper, string $type): array
    {
        $output->writeln('');
        $output->writeln("<info>📦 Importar .zip local ({$type})</info>");
        $output->writeln('');
        
        $zipQuestion = new Question('<fg=yellow>Path al archivo .zip:</> ');
        $zipPath = $helper->ask($input, $output, $zipQuestion);
        
        if (empty($zipPath) || !file_exists($zipPath)) {
            $output->writeln('<error>Archivo no encontrado</error>');
            return [];
        }
        
        $cacheService = new \Roots\BedrockCli\Services\PremiumCacheService();
        
        try {
            $output->writeln('  ⏳ Extrayendo metadata...');
            $metadata = $cacheService->extractMetadataFromZip($zipPath, $type);
            
            $name = $metadata['name'] ?? basename($zipPath, '.zip');
            $version = $metadata['version'] ?? 'imported-zip';
            
            if ($metadata['name']) {
                $output->writeln("  ✓ Nombre detectado: {$name}");
            }
            if ($metadata['version']) {
                $output->writeln("  ✓ Versión detectada: {$version}");
            } else {
                $output->writeln("  ⚠️  Versión no detectada, usando: imported-zip");
            }
            
            $output->writeln('  ⏳ Importando a cache...');
            $cacheService->importToCache($zipPath, $name, $version, $type);
            $output->writeln("  ✓ {$name} {$version} importado");
            
            return [[
                'name' => $name,
                'version' => $version,
                'source' => 'cache',
                'original_url' => 'local:' . basename($zipPath)
            ]];
            
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return [];
        }
    }

    private function selectFromCache(InputInterface $input, OutputInterface $output, $helper, string $type): array
    {
        $output->writeln('');
        $output->writeln("<info>💾 Seleccionar desde cache ({$type})</info>");
        $output->writeln('');
        $output->writeln('<comment>Función pendiente de implementar</comment>');
        return [];
    }
}
