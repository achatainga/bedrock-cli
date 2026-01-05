<?php

namespace Roots\BedrockCli\Services\Management;

use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Enums\ExecutionMode;

class ContextDetector
{
    private ?string $projectRoot = null;
    private ?array $profileData = null;
    private DockerService $dockerService;

    public function __construct(DockerService $dockerService)
    {
        $this->dockerService = $dockerService;
    }

    public function detectExecutionMode(): ExecutionMode
    {
        $root = getcwd(); // Use current directory instead of getProjectRoot()
        
        // 1. Sin docker-compose.yml -> NATIVE
        if (!file_exists($root . '/docker-compose.yml')) {
            return ExecutionMode::NATIVE;
        }

        // 2. Leer configuración de BD del .env
        $env = $this->readEnv($root);
        $dbHost = $env['DB_HOST'] ?? '';

        // 3. Lógica Híbrida: Hay Docker, pero config apunta a local
        $isLocalHost = in_array($dbHost, ['127.0.0.1', 'localhost']);
        
        if ($isLocalHost) {
            return ExecutionMode::HYBRID;
        }

        // 4. Default a Docker si apunta al servicio interno (ej: 'mysql')
        return ExecutionMode::DOCKER;
    }

    private function readEnv(string $path): array
    {
        if (!file_exists($path . '/.env')) return [];
        $env = [];
        $lines = file($path . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $env[trim($key)] = trim($value, "'\"");
            }
        }
        return $env;
    }

    public function isBedrockProject(?string $path = null): bool
    {
        $path = $path ?? getcwd();
        
        if (file_exists($path . '/composer.json')) {
            $composer = json_decode(file_get_contents($path . '/composer.json'), true);
            return isset($composer['require']['roots/bedrock']) || 
                   isset($composer['require']['roots/wordpress']);
        }
        
        return false;
    }

    public function getProjectRoot(?string $path = null): ?string
    {
        if ($this->projectRoot !== null) {
            return $this->projectRoot;
        }

        $path = $path ?? getcwd();
        $maxDepth = 5;
        $current = $path;

        for ($i = 0; $i < $maxDepth; $i++) {
            if ($this->isBedrockProject($current)) {
                $this->projectRoot = $current;
                return $current;
            }

            $parent = dirname($current);
            if ($parent === $current) {
                break;
            }
            $current = $parent;
        }

        return null;
    }

    public function hasActiveProfile(?string $path = null): bool
    {
        $root = $this->getProjectRoot($path);
        if (!$root) {
            return false;
        }

        return file_exists($root . '/.bedrock/profile.json');
    }

    public function getActiveProfile(?string $path = null): ?array
    {
        if ($this->profileData !== null) {
            return $this->profileData;
        }

        $root = $this->getProjectRoot($path);
        if (!$root || !$this->hasActiveProfile($path)) {
            return null;
        }

        $content = file_get_contents($root . '/.bedrock/profile.json');
        $this->profileData = json_decode($content, true);

        return $this->profileData;
    }

    public function getContext(?string $path = null): string
    {
        if (!$this->isBedrockProject($path)) {
            return 'none';
        }

        return $this->hasActiveProfile($path) ? 'project_with_profile' : 'project_without_profile';
    }

    public function getComposerJson(?string $path = null): ?array
    {
        $root = $this->getProjectRoot($path);
        if (!$root) {
            return null;
        }

        $composerPath = $root . '/composer.json';
        if (!file_exists($composerPath)) {
            return null;
        }

        return json_decode(file_get_contents($composerPath), true);
    }
}
