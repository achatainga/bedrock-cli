<?php

namespace Roots\BedrockCli\Services\Management;

use RuntimeException;

class PluginManager
{
    private ManagementService $management;

    public function __construct(ManagementService $management)
    {
        $this->management = $management;
    }

    public function add(string $slug, string $type = 'wpackagist-plugin', ?string $version = null): void
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

        $this->management->addChange('plugin_added', [
            'slug' => $slug,
            'package' => $package,
            'version' => $versionConstraint
        ]);
    }

    public function remove(string $slug, string $type = 'wpackagist-plugin'): void
    {
        $package = $this->buildPackageName($slug, $type);

        $this->management->updateComposerJson(function ($composer) use ($package) {
            if (isset($composer['require'][$package])) {
                unset($composer['require'][$package]);
            }

            return $composer;
        });

        $this->management->addChange('plugin_removed', [
            'slug' => $slug,
            'package' => $package
        ]);
    }

    public function list(): array
    {
        $composer = $this->management->readComposerJson();
        $plugins = [];

        if (!isset($composer['require'])) {
            return $plugins;
        }

        foreach ($composer['require'] as $package => $version) {
            if (str_starts_with($package, 'wpackagist-plugin/')) {
                $slug = str_replace('wpackagist-plugin/', '', $package);
                $plugins[$slug] = [
                    'slug' => $slug,
                    'package' => $package,
                    'version' => $version,
                    'type' => 'public'
                ];
            }
        }

        return $plugins;
    }

    public function exists(string $slug, string $type = 'wpackagist-plugin'): bool
    {
        $package = $this->buildPackageName($slug, $type);
        $composer = $this->management->readComposerJson();

        return isset($composer['require'][$package]);
    }

    public function getVersion(string $slug, string $type = 'wpackagist-plugin'): ?string
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
            'wpackagist-plugin' => "wpackagist-plugin/{$slug}",
            'custom' => $slug,
            default => throw new RuntimeException("Tipo de plugin desconocido: {$type}")
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
