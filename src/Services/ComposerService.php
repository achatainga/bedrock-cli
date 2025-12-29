<?php

namespace Roots\BedrockCli\Services;

class ComposerService
{
    private PremiumCacheService $cacheService;
    
    public function __construct()
    {
        $this->cacheService = new PremiumCacheService();
    }
    public function generateFromProfile(\Roots\BedrockCli\DTOs\Profile|array $profile, string $projectPath): void
    {
        $composerJsonPath = $projectPath . '/composer.json';
        
        if (!file_exists($composerJsonPath)) {
            throw new \RuntimeException("composer.json no existe en {$projectPath}");
        }

        $composerData = json_decode(file_get_contents($composerJsonPath), true);
        
        // Determinar si usar symlink o copiar
        $useSymlink = !($profile['docker_mode'] ?? false);
        
        // Guardar paquetes core de Bedrock
        $corePackages = [
            'php', 'composer/installers', 'vlucas/phpdotenv', 'oscarotero/env',
            'roots/bedrock-autoloader', 'roots/bedrock-disallow-indexing',
            'roots/wordpress', 'roots/wp-config', 'roots/acorn',
            'rhubarbgroup/redis-cache'
        ];
        
        $newRequire = [];
        foreach ($corePackages as $pkg) {
            if (isset($composerData['require'][$pkg])) {
                $newRequire[$pkg] = $composerData['require'][$pkg];
            }
        }
        
        // Mantener temas wpackagist
        foreach ($composerData['require'] as $pkg => $version) {
            if (str_starts_with($pkg, 'wpackagist-theme/')) {
                $newRequire[$pkg] = $version;
            }
        }
        
        // REEMPLAZAR require con solo core + temas
        $composerData['require'] = $newRequire;
        
        // Limpiar repositories (mantener solo wpackagist)
        $composerData['repositories'] = [];

        // Agregar wpackagist.org
        $this->ensureWpackagist($composerData);

        // Agregar repositorios del profile
        if (!empty($profile['repositories'])) {
            foreach ($profile['repositories'] as $repo) {
                $composerData['repositories'][] = $repo;
            }
        }

        // Agregar repositorios para plugins premium
        if (!empty($profile['plugins']['premium'])) {
            foreach ($profile['plugins']['premium'] as $plugin) {
                if ($plugin['source'] === 'cache') {
                    // Extraer y preparar plugin desde caché
                    $this->cacheService->extractPlugin($plugin['name'], $plugin['version']);
                    $vendor = 'cached';
                    $this->cacheService->clearPluginComposerJson($plugin['name'], $plugin['version']);
                    $this->cacheService->ensureComposerJson($plugin['name'], $plugin['version'], $vendor);
                    
                    $cachePath = $this->cacheService->getCachePath($plugin['name'], $plugin['version']);
                    $repo = ['type' => 'path', 'url' => $cachePath, 'options' => ['symlink' => $useSymlink]];
                    
                    if (!$this->repositoryExists($composerData['repositories'], $repo)) {
                        $composerData['repositories'][] = $repo;
                    }
                    
                    $package = "{$vendor}/{$plugin['name']}";
                    $composerData['require'][$package] = $plugin['version'];
                } elseif ($plugin['source'] === 'vcs') {
                    $repo = ['type' => 'vcs', 'url' => $plugin['url']];
                    if (!$this->repositoryExists($composerData['repositories'], $repo)) {
                        $composerData['repositories'][] = $repo;
                    }
                    // NO construir package name desde URL - composer lo resuelve desde composer.json del repo
                    // El profile debe tener el package name correcto en require
                } elseif ($plugin['source'] === 'path') {
                    $repo = ['type' => 'path', 'url' => $plugin['path'], 'options' => ['symlink' => $useSymlink]];
                    if (!$this->repositoryExists($composerData['repositories'], $repo)) {
                        $composerData['repositories'][] = $repo;
                    }
                    $package = "local/{$plugin['name']}";
                    $composerData['require'][$package] = $plugin['version'];
                }
            }
        }

        // Agregar repositorios para themes premium
        if (!empty($profile['themes']['premium'])) {
            foreach ($profile['themes']['premium'] as $theme) {
                if ($theme['source'] === 'cache') {
                    $this->cacheService->extractTheme($theme['name'], $theme['version']);
                    $vendor = 'cached';
                    $this->cacheService->clearThemeComposerJson($theme['name'], $theme['version']);
                    $this->cacheService->ensureThemeComposerJson($theme['name'], $theme['version'], $vendor);
                    
                    $cachePath = $this->cacheService->getThemeCachePath($theme['name'], $theme['version']);
                    $repo = ['type' => 'path', 'url' => $cachePath, 'options' => ['symlink' => $useSymlink]];
                    
                    if (!$this->repositoryExists($composerData['repositories'], $repo)) {
                        $composerData['repositories'][] = $repo;
                    }
                    
                    $package = "{$vendor}/{$theme['name']}";
                    $composerData['require'][$package] = $theme['version'];
                }
            }
        }
        
        // Agregar plugins custom desde repositorio path
        if (!empty($profile['plugins']['custom']) && !empty($profile['repositories'])) {
            foreach ($profile['plugins']['custom'] as $pluginName) {
                // Buscar el repository path de este plugin
                $pluginPath = null;
                foreach ($profile['repositories'] as $repo) {
                    if ($repo['type'] === 'path' && str_contains($repo['url'], $pluginName)) {
                        $pluginPath = $repo['url'];
                        break;
                    }
                }
                
                if ($pluginPath) {
                    // Leer vendor del composer.json del plugin
                    $vendor = $this->extractVendorFromPluginComposer($pluginPath);
                    $package = "{$vendor}/{$pluginName}";
                    $composerData['require'][$package] = '*';
                }
            }
        }

        // Agregar dependencias del profile (incluye VCS packages con nombre correcto)
        if (!empty($profile['require'])) {
            foreach ($profile['require'] as $package => $version) {
                // Skip si ya fue agregado por plugins premium
                if (!isset($composerData['require'][$package])) {
                    $composerData['require'][$package] = $version;
                }
            }
        }

        // Guardar composer.json actualizado
        file_put_contents(
            $composerJsonPath,
            json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function ensureWpackagist(array &$composerData): void
    {
        if (!isset($composerData['repositories'])) {
            $composerData['repositories'] = [];
        }

        $hasWpackagist = false;
        foreach ($composerData['repositories'] as $repo) {
            if (isset($repo['url']) && strpos($repo['url'], 'wpackagist.org') !== false) {
                $hasWpackagist = true;
                break;
            }
        }

        if (!$hasWpackagist) {
            array_unshift($composerData['repositories'], [
                'type' => 'composer',
                'url' => 'https://wpackagist.org',
                'only' => ['wpackagist-plugin/*', 'wpackagist-theme/*']
            ]);
        }
    }

    public function copyProfileToProject(\Roots\BedrockCli\DTOs\Profile|array $profile, string $projectPath): void
    {
        $bedrockDir = $projectPath . '/.bedrock';
        
        if (!is_dir($bedrockDir)) {
            mkdir($bedrockDir, 0755, true);
        }

        // IMPORTANTE: Guardar el nuevo profile DESPUÉS de que generateFromProfile() 
        // haya leído el profile anterior
        file_put_contents(
            $bedrockDir . '/profile.json',
            json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        // Copiar archivos .zip custom
        $this->copyCustomZipFiles($profile, $projectPath);
    }
    
    public function copyCustomZipFiles(\Roots\BedrockCli\DTOs\Profile|array $profile, string $projectPath): void
    {
        // Copiar plugins .zip
        if (!empty($profile['plugins']['custom'])) {
            $pluginsDir = $projectPath . '/plugins';
            if (!is_dir($pluginsDir)) {
                mkdir($pluginsDir, 0755, true);
                file_put_contents($pluginsDir . '/.gitkeep', '');
            }
            
            foreach ($profile['plugins']['custom'] as $plugin) {
                if (is_array($plugin) && isset($plugin['source']) && $plugin['source'] === 'zip' && isset($plugin['path'])) {
                    $zipPath = $plugin['path'];
                    if (file_exists($zipPath)) {
                        copy($zipPath, $pluginsDir . '/' . basename($zipPath));
                    }
                }
            }
            
            $this->ensureGitignore($pluginsDir);
        }
        
        // Copiar themes .zip
        if (!empty($profile['themes']['custom'])) {
            $themesDir = $projectPath . '/themes';
            if (!is_dir($themesDir)) {
                mkdir($themesDir, 0755, true);
                file_put_contents($themesDir . '/.gitkeep', '');
            }
            
            foreach ($profile['themes']['custom'] as $theme) {
                if (is_array($theme) && isset($theme['source']) && $theme['source'] === 'zip' && isset($theme['path'])) {
                    $zipPath = $theme['path'];
                    if (file_exists($zipPath)) {
                        copy($zipPath, $themesDir . '/' . basename($zipPath));
                    }
                }
            }
            
            $this->ensureGitignore($themesDir);
        }
    }
    
    private function ensureGitignore(string $dir): void
    {
        $gitignorePath = $dir . '/.gitignore';
        $content = "*\n!.gitkeep\n!.gitignore\n";
        
        if (!file_exists($gitignorePath)) {
            file_put_contents($gitignorePath, $content);
        }
    }
    
    private function extractVendorFromUrl(string $url): string
    {
        // Extraer vendor de URLs como: https://gitlab.com/detodo24dev/repo.git
        // o https://github.com/vendor/repo.git
        if (preg_match('#(?:gitlab\.com|github\.com)/([^/]+)/#', $url, $matches)) {
            return $matches[1];
        }
        
        // Fallback si no se puede extraer
        return 'vendor';
    }
    
    private function extractVendorFromPath(string $path): string
    {
        // Extraer vendor del nombre de la carpeta padre
        // Ej: C:\code\dt24\plugin-name -> dt24
        // Ej: /var/www/dt24/plugin-name -> dt24
        $parts = explode(DIRECTORY_SEPARATOR, rtrim($path, DIRECTORY_SEPARATOR));
        
        // Si el path termina en un plugin específico, tomar el penúltimo
        // Si es la carpeta madre, tomar el último
        if (count($parts) >= 2) {
            return $parts[count($parts) - 2];
        }
        
        return 'vendor';
    }
    
    private function repositoryExists(array $repositories, array $newRepo): bool
    {
        $newUrl = $this->normalizePath($newRepo['url']);
        
        foreach ($repositories as $repo) {
            $repoUrl = $this->normalizePath($repo['url']);
            if ($repo['type'] === $newRepo['type'] && $repoUrl === $newUrl) {
                return true;
            }
        }
        return false;
    }
    
    private function normalizePath(string $path): string
    {
        // Normalizar a forward slashes para comparación consistente
        return str_replace('\\', '/', $path);
    }
    
    private function extractVendorFromPluginComposer(string $pluginPath): string
    {
        $composerPath = $pluginPath . '/composer.json';
        
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if (!empty($composer['name'])) {
                // vendor/package → vendor
                return explode('/', $composer['name'])[0];
            }
        }
        
        // Fallback: extraer del path
        return $this->extractVendorFromPath($pluginPath);
    }
    
    private function cleanPreviousProfilePackages(array &$composerData, string $projectPath): void
    {
        // Paquetes core de Bedrock que NUNCA se deben eliminar
        $corePackages = [
            'php', 'composer/installers', 'vlucas/phpdotenv', 'oscarotero/env',
            'roots/bedrock-autoloader', 'roots/bedrock-disallow-indexing',
            'roots/wordpress', 'roots/wp-config', 'roots/acorn',
            'wpackagist-theme/twentytwentyfive', 'rhubarbgroup/redis-cache',
            'roave/security-advisories', 'laravel/pint'
        ];
        
        // Eliminar todos los plugins (wpackagist-plugin/*)
        foreach (array_keys($composerData['require']) as $package) {
            if (str_starts_with($package, 'wpackagist-plugin/')) {
                unset($composerData['require'][$package]);
            }
        }
        
        // Eliminar todos los paquetes que NO sean core
        foreach (array_keys($composerData['require']) as $package) {
            if (!in_array($package, $corePackages) && !str_starts_with($package, 'wpackagist-theme/')) {
                unset($composerData['require'][$package]);
            }
        }
        
        // Limpiar repositories (mantener solo wpackagist)
        $newRepositories = [];
        foreach ($composerData['repositories'] as $repo) {
            if (isset($repo['url']) && strpos($repo['url'], 'wpackagist.org') !== false) {
                $newRepositories[] = $repo;
            }
        }
        $composerData['repositories'] = $newRepositories;
    }
}
