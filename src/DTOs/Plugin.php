<?php

namespace Roots\BedrockCli\DTOs;

class Plugin
{
    public function __construct(
        public readonly string $slug,
        public readonly string $version,
        public readonly string $source, // 'public', 'premium', 'custom'
        public readonly ?string $url = null,
        public readonly bool $isMuPlugin = false,
        public readonly bool $isActive = false
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            slug: $data['slug'] ?? '',
            version: $data['version'] ?? '*',
            source: $data['source'] ?? 'public',
            url: $data['url'] ?? null,
            isMuPlugin: $data['is_mu_plugin'] ?? false,
            isActive: $data['is_active'] ?? false
        );
    }

    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'version' => $this->version,
            'source' => $this->source,
            'url' => $this->url,
            'is_mu_plugin' => $this->isMuPlugin,
            'is_active' => $this->isActive
        ];
    }

    public function getPackageName(): string
    {
        return match($this->source) {
            'public' => "wpackagist-plugin/{$this->slug}",
            'premium', 'custom' => $this->url ?? $this->slug,
            default => $this->slug
        };
    }
}