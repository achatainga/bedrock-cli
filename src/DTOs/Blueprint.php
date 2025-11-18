<?php

namespace Roots\BedrockCli\DTOs;

class Blueprint
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly string $version,
        public readonly array $plugins = [],
        public readonly array $themes = [],
        public readonly array $config = [],
        public readonly array $files = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            version: $data['version'] ?? '1.0.0',
            plugins: $data['plugins'] ?? [],
            themes: $data['themes'] ?? [],
            config: $data['config'] ?? [],
            files: $data['files'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'plugins' => $this->plugins,
            'themes' => $this->themes,
            'config' => $this->config,
            'files' => $this->files
        ];
    }
}