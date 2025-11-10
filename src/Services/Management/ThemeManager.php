<?php

namespace Roots\BedrockCli\Services\Management;

use RuntimeException;

class ThemeManager
{
    private ManagementService $management;

    public function __construct(ManagementService $management)
    {
        $this->management = $management;
    }

    public function add(string $slug, string $type = 'wpackagist-theme', ?string $version = null): void
    {
        $package = $this->buildPackageName($slug, $type);
        $versionConstraint = $version ?? '*';

        $this->management->updateComposerJson(function ($composer) use ($package, $versionConstraint) {
            if (!isset($composer['require'])) {
                $composer['require'] = [];
            }

            $composer['require'][$package] = $versionConstraint;

            return $composer;
        });

        $this->management->addChange('theme_added', [
            'slug' => $slug,
            'package' => $package,
            'version' => $versionConstraint
        ]);
    }

    public function remove(string $slug, string $type = 'wpackagist-theme'): void
    {
        $package = $this->buildPackageName($slug, $type);

        $this->management->updateComposerJson(function ($composer) use ($package) {
            if (isset($composer['require'][$package])) {
                unset($composer['require'][$package]);
            }

            return $composer;
        });

        $this->management->addChange('theme_removed', [
            'slug' => $slug,
            'package' => $package
        ]);
    }

    public function list(): array
    {
        $composer = $this->management->readComposerJson();
        $themes = [];

        if (!isset($composer['require'])) {
            return $themes;
        }

        // Obtener lista de themes reales desde el directorio
        $themesDir = getcwd() . '/web/app/themes';
        $installedThemes = [];
        
        if (is_dir($themesDir)) {
            foreach (scandir($themesDir) as $dir) {
                if ($dir !== '.' && $dir !== '..' && is_dir($themesDir . '/' . $dir)) {
                    $installedThemes[] = $dir;
                }
            }
        }

        foreach ($composer['require'] as $package => $version) {
            $slug = null;
            $type = null;
            
            if (str_starts_with($package, 'wpackagist-theme/')) {
                $slug = str_replace('wpackagist-theme/', '', $package);
                $type = 'public';
            } elseif (str_starts_with($package, 'cached/')) {
                $slug = str_replace('cached/', '', $package);
                $type = 'cached';
            }
            
            // Solo agregar si realmente existe en web/app/themes
            if ($slug && in_array($slug, $installedThemes)) {
                $themes[$slug] = [
                    'slug' => $slug,
                    'package' => $package,
                    'version' => $version,
                    'type' => $type
                ];
            }
        }

        return $themes;
    }

    public function exists(string $slug, string $type = 'wpackagist-theme'): bool
    {
        $package = $this->buildPackageName($slug, $type);
        $composer = $this->management->readComposerJson();

        return isset($composer['require'][$package]);
    }

    public function getVersion(string $slug, string $type = 'wpackagist-theme'): ?string
    {
        $package = $this->buildPackageName($slug, $type);
        $composer = $this->management->readComposerJson();

        return $composer['require'][$package] ?? null;
    }

    private function buildPackageName(string $slug, string $type): string
    {
        if (str_contains($slug, '/')) {
            return $slug;
        }

        return match ($type) {
            'wpackagist-theme' => "wpackagist-theme/{$slug}",
            'cached' => "cached/{$slug}",
            'custom' => $slug,
            default => throw new RuntimeException("Tipo de theme desconocido: {$type}")
        };
    }

    public function addRepository(array $repository): void
    {
        $this->management->updateComposerJson(function ($composer) use ($repository) {
            if (!isset($composer['repositories'])) {
                $composer['repositories'] = [];
            }

            $composer['repositories'][] = $repository;

            return $composer;
        });

        $this->management->addChange('repository_added', $repository);
    }
}
