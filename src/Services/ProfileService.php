<?php

namespace BedrockCli\Services;

use RuntimeException;

class ProfileService
{
    private string $bedrockCliPath;
    private string $profilesPath;
    private string $configPath;

    public function __construct()
    {
        $this->bedrockCliPath = $this->getBedrockCliPath();
        $this->profilesPath = $this->bedrockCliPath . '/profiles';
        $this->configPath = $this->bedrockCliPath . '/config.json';
        
        $this->ensureDirectoryStructure();
        $this->ensureDefaultProfile();
    }

    private function getBedrockCliPath(): string
    {
        $home = $this->getHomeDirectory();
        return $home . '/.bedrock-cli';
    }

    private function getHomeDirectory(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return getenv('USERPROFILE') ?: getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }
        
        return getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];
    }

    private function ensureDirectoryStructure(): void
    {
        if (!is_dir($this->bedrockCliPath)) {
            mkdir($this->bedrockCliPath, 0755, true);
        }

        if (!is_dir($this->profilesPath)) {
            mkdir($this->profilesPath, 0755, true);
        }

        if (!file_exists($this->configPath)) {
            $defaultConfig = [
                'version' => '1.0',
                'created_at' => date('Y-m-d H:i:s'),
                'default_profile' => 'default'
            ];
            file_put_contents($this->configPath, json_encode($defaultConfig, JSON_PRETTY_PRINT));
        }
    }

    public function getProfilesPath(): string
    {
        return $this->profilesPath;
    }

    public function profileExists(string $name): bool
    {
        return file_exists($this->profilesPath . '/' . $name . '.json');
    }

    public function loadProfile(string $name): array
    {
        if (!$this->profileExists($name)) {
            throw new RuntimeException("Profile '{$name}' no existe");
        }

        $content = file_get_contents($this->profilesPath . '/' . $name . '.json');
        $profile = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Profile '{$name}' contiene JSON inválido: " . json_last_error_msg());
        }

        return $profile;
    }

    public function saveProfile(string $name, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Error al codificar profile: " . json_last_error_msg());
        }

        file_put_contents($this->profilesPath . '/' . $name . '.json', $json);
    }

    public function listProfiles(): array
    {
        $profiles = [];
        $files = glob($this->profilesPath . '/*.json');

        foreach ($files as $file) {
            $name = basename($file, '.json');
            try {
                $profile = $this->loadProfile($name);
                $profiles[$name] = [
                    'name' => $name,
                    'description' => $profile['description'] ?? 'Sin descripción',
                    'file' => $file
                ];
            } catch (RuntimeException $e) {
                $profiles[$name] = [
                    'name' => $name,
                    'description' => 'Error: ' . $e->getMessage(),
                    'file' => $file
                ];
            }
        }

        return $profiles;
    }

    public function deleteProfile(string $name): bool
    {
        if (!$this->profileExists($name)) {
            throw new RuntimeException("Profile '{$name}' no existe");
        }

        return unlink($this->profilesPath . '/' . $name . '.json');
    }

    public function getConfig(): array
    {
        $content = file_get_contents($this->configPath);
        return json_decode($content, true);
    }

    public function updateConfig(array $data): void
    {
        $current = $this->getConfig();
        $updated = array_merge($current, $data);
        file_put_contents($this->configPath, json_encode($updated, JSON_PRETTY_PRINT));
    }

    private function ensureDefaultProfile(): void
    {
        if ($this->profileExists('default')) {
            return;
        }

        $sourceProfile = dirname(__DIR__, 2) . '/profiles/default.json';
        
        if (!file_exists($sourceProfile)) {
            return;
        }

        copy($sourceProfile, $this->profilesPath . '/default.json');
    }
}
