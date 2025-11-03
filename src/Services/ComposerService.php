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
    }
}
