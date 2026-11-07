<?php

namespace Roots\BedrockCli\DTOs;

class Profile implements \ArrayAccess
{
    public function __construct(
        public string $name,
        public string $description,
        public array $plugins = [],
        public array $themes = [],
        public array $repositories = [],
        public array $config = [],
        public bool $docker_mode = false
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            description: $data['description'] ?? '',
            plugins: $data['plugins'] ?? [],
            themes: $data['themes'] ?? [],
            repositories: $data['repositories'] ?? [],
            config: $data['config'] ?? [],
            docker_mode: $data['docker_mode'] ?? false
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
    
    // ArrayAccess implementation for backward compatibility
    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }
    
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset ?? null;
    }
    
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (property_exists($this, $offset)) {
            $this->$offset = $value;
        }
    }
    
    public function offsetUnset(mixed $offset): void
    {
        // Not supported for DTO
    }
}