<?php

namespace Roots\BedrockCli\Services\Management;

use RuntimeException;

class ManagementService
{
    private ContextDetector $contextDetector;
    private array $changes = [];
    private bool $dryRun = false;

    public function __construct(ContextDetector $contextDetector)
    {
        $this->contextDetector = $contextDetector;
    }

    public function requireBedrockProject(): void
    {
        if (!$this->contextDetector->isBedrockProject()) {
            throw new RuntimeException(
                "Este comando debe ejecutarse dentro de un proyecto Bedrock.\n" .
                "Usa 'bedrock new <nombre>' para crear un nuevo proyecto."
            );
        }
    }

    public function getProjectRoot(): string
    {
        $root = $this->contextDetector->getProjectRoot();
        if (!$root) {
            throw new RuntimeException("No se pudo determinar la raíz del proyecto Bedrock");
        }
        return $root;
    }

    public function addChange(string $type, array $data): void
    {
        $this->changes[] = [
            'type' => $type,
            'data' => $data,
            'timestamp' => time()
        ];
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function clearChanges(): void
    {
        $this->changes = [];
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    public function saveProfileMetadata(string $profileName, array $metadata = []): void
    {
        $root = $this->getProjectRoot();
        $bedrockDir = $root . '/.bedrock';

        if (!is_dir($bedrockDir)) {
            mkdir($bedrockDir, 0755, true);
        }

        $data = array_merge([
            'profile' => $profileName,
            'applied_at' => date('Y-m-d H:i:s'),
            'version' => '1.0'
        ], $metadata);

        file_put_contents(
            $bedrockDir . '/profile.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function getProfileMetadata(): ?array
    {
        return $this->contextDetector->getActiveProfile();
    }

    public function updateComposerJson(callable $callback): void
    {
        $root = $this->getProjectRoot();
        $composerPath = $root . '/composer.json';

        if (!file_exists($composerPath)) {
            throw new RuntimeException("composer.json no encontrado en {$root}");
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("composer.json inválido: " . json_last_error_msg());
        }

        $composer = $callback($composer);

        if (!$this->dryRun) {
            file_put_contents(
                $composerPath,
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
            );
        }
    }

    public function readComposerJson(): array
    {
        $composer = $this->contextDetector->getComposerJson();
        if (!$composer) {
            throw new RuntimeException("No se pudo leer composer.json");
        }
        return $composer;
    }
}
