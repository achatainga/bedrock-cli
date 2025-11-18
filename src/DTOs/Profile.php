<?php

namespace Roots\BedrockCli\DTOs;

class Profile
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $plugins = [],
        public readonly array $themes = [],
        public readonly array $repositories = [],
        public readonly array $config = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            plugins: $data['plugins'] ?? [],
            themes: $data['themes'] ?? [],
            repositories: $data['repositories'] ?? [],
            config: $data['config'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'plugins' => $this->plugins,
            'themes' => $this->themes,
            'repositories' => $this->repositories,
            'config' => $this->config
        ];
    }
}