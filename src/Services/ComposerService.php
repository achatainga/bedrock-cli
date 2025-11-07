<?php

namespace Roots\BedrockCli\Services;

class ComposerService
{
    public function generateFromProfile(array $profile, string $projectPath): void
    {
        $composerJsonPath = $projectPath . '/composer.json';
        
        if (!file_exists($composerJsonPath)) {
            throw new \RuntimeException("composer.json no existe en {$projectPath}");
        }

        $composerData = json_decode(file_get_contents($composerJsonPath), true);

        // Agregar wpackagist.org si no existe
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
                if ($plugin['source'] === 'vcs') {
                    $composerData['repositories'][] = ['type' => 'vcs', 'url' => $plugin['url']];
                    $package = "detodo24/{$plugin['name']}";
                    $composerData['require'][$package] = $plugin['version'];
                } elseif ($plugin['source'] === 'path') {
                    $composerData['repositories'][] = ['type' => 'path', 'url' => $plugin['path'], 'options' => ['symlink' => true]];
                    $package = "local/{$plugin['name']}";
                    $composerData['require'][$package] = $plugin['version'];
                }
            }
        }

        // Agregar plugins custom desde repositorio path
        if (!empty($profile['plugins']['custom']) && !empty($profile['repositories'])) {
            foreach ($profile['plugins']['custom'] as $pluginName) {
                $package = "detodo24/{$pluginName}";
                $composerData['require'][$package] = '*';
            }
        }

        // Agregar dependencias del profile
        if (!empty($profile['require'])) {
            foreach ($profile['require'] as $package => $version) {
                $composerData['require'][$package] = $version;
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

    public function copyProfileToProject(array $profile, string $projectPath): void
    {
        $bedrockDir = $projectPath . '/.bedrock';
        
        if (!is_dir($bedrockDir)) {
            mkdir($bedrockDir, 0755, true);
        }

        file_put_contents(
            $bedrockDir . '/profile.json',
            json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        
        // Copiar archivos .zip custom
        $this->copyCustomZipFiles($profile, $projectPath);
    }
    
    public function copyCustomZipFiles(array $profile, string $projectPath): void
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
}
