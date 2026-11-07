<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\Blueprints;

use RuntimeException;

class VerticalRegistry
{
    private string $configPath;
    private string $pluginPrefix;
    private ?array $data = null;

    public function __construct(?string $configPath = null, ?string $pluginPrefix = null)
    {
        $this->configPath = $configPath ?? dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'verticals.json';
        $envPrefix = getenv('BEDROCK_PLUGIN_PREFIX');
        $this->pluginPrefix = $pluginPrefix ?? ($envPrefix !== false && $envPrefix !== '' ? (string) $envPrefix : 'core-');
    }

    public function getPluginPrefix(): string
    {
        return $this->pluginPrefix;
    }

    public function setPluginPrefix(string $pluginPrefix): self
    {
        $this->pluginPrefix = $pluginPrefix;
        return $this;
    }

    /**
     * Normalizes a plugin slug by abstracting static branding prefixes to the active prefix.
     */
    public function normalizePlugin(string $plugin): string
    {
        if (str_starts_with($plugin, 'dt24-')) {
            return $this->pluginPrefix . substr($plugin, 5);
        }

        if (str_starts_with($plugin, 'core-')) {
            return $this->pluginPrefix . substr($plugin, 5);
        }

        return $plugin;
    }

    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        if (!file_exists($this->configPath)) {
            throw new RuntimeException("Verticals configuration file not found at: {$this->configPath}");
        }

        $raw = file_get_contents($this->configPath);
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new RuntimeException("Invalid JSON in verticals config: " . json_last_error_msg());
        }

        $this->data = $decoded;
        return $this->data;
    }

    public function getSectors(): array
    {
        $data = $this->load();
        return $data['macro_sectors'] ?? [];
    }

    public function getAllVerticals(): array
    {
        $data = $this->load();
        return $data['verticals'] ?? [];
    }

    public function hasVertical(string $id): bool
    {
        $verticals = $this->getAllVerticals();
        return isset($verticals[$id]);
    }

    public function getVertical(string $id): ?array
    {
        $verticals = $this->getAllVerticals();
        return $verticals[$id] ?? null;
    }

    public function getVerticalsBySector(string $sector): array
    {
        $all = $this->getAllVerticals();
        return array_filter($all, fn(array $v) => ($v['sector'] ?? '') === $sector);
    }

    public function count(): int
    {
        return count($this->getAllVerticals());
    }

    /**
     * Synthesizes a full Bedrock Profile array out of a vertical specification.
     */
    public function synthesizeProfile(string $verticalId, string $projectName): array
    {
        $vertical = $this->getVertical($verticalId);
        if (!$vertical) {
            throw new RuntimeException("Vertical '{$verticalId}' not found in registry.");
        }

        $plugins = $vertical['plugins'] ?? [];
        $publicPlugins = [];
        $customPlugins = [];

        foreach ($plugins as $plugin) {
            $normalized = $this->normalizePlugin($plugin);
            if ($normalized === 'woocommerce') {
                $publicPlugins[] = 'woocommerce';
            } else {
                $customPlugins[] = $normalized;
            }
        }

        $require = [];
        if (in_array('woocommerce', $publicPlugins, true)) {
            $require['wpackagist-plugin/woocommerce'] = '^8.5';
        }

        return [
            'name' => "vertical-{$verticalId}",
            'vertical_id' => $verticalId,
            'description' => $vertical['name'] . ' - ' . $vertical['description'],
            'sector' => $vertical['sector'] ?? 'general',
            'admin_stack' => $vertical['admin_stack'] ?? 'acorn-blade-modern',
            'repositories' => [],
            'require' => $require,
            'plugins' => [
                'public' => $publicPlugins,
                'premium' => [],
                'custom' => $customPlugins,
            ],
            'theme' => [
                'name' => $vertical['theme'] ?? 'twentytwentyfour',
                'type' => 'public',
                'license_env' => null,
            ],
            'virtual_assistant' => [
                'capabilities' => $vertical['va_capabilities'] ?? [],
                'gateways' => ['whatsapp', 'telegram', 'web'],
                'model' => 'gemini-2.5-flash',
            ],
            'crm_features' => $vertical['crm_features'] ?? [],
            'devops' => $vertical['devops'] ?? [
                'ssl' => 'auto',
                'redis_cache' => true,
                'backup_retention_days' => 30,
            ],
            'blueprints' => [
                'production' => [
                    'snapshot' => null,
                    'seeders' => ['CoreSeeder'],
                    'users' => [
                        'admin' => 'administrator'
                    ],
                ],
                'staging' => [
                    'snapshot' => null,
                    'seeders' => ['CoreSeeder'],
                    'faker_records' => 25,
                ],
                'development' => [
                    'snapshot' => null,
                    'seeders' => ['CoreSeeder'],
                    'users' => [
                        'admin' => 'administrator',
                        'dev' => 'administrator'
                    ],
                ],
            ],
        ];
    }
}
