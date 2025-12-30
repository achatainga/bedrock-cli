<?php

namespace Roots\BedrockCli\Services;

use RuntimeException;
use Roots\BedrockCli\DTOs\Profile;

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
        // Detectar WSL: PHP_OS_FAMILY es Linux pero existe /mnt/c/
        if (PHP_OS_FAMILY === 'Linux' || is_dir('/proc/version')) {
            return getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];
        }
        
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

    public function loadProfile(string $name): Profile
    {
        if (!$this->profileExists($name)) {
            throw new RuntimeException("Profile '{$name}' no existe");
        }

        $content = file_get_contents($this->profilesPath . '/' . $name . '.json');
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Profile '{$name}' contiene JSON inválido: " . json_last_error_msg());
        }

        // Asegurar que docker_mode existe (default: false para compatibilidad)
        if (!isset($data['docker_mode'])) {
            $data['docker_mode'] = false;
        }

        return Profile::fromArray($data);
    }

    public function saveProfile(string $name, Profile $profile): void
    {
        $json = json_encode($profile->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Error al codificar profile: " . json_last_error_msg());
        }

        file_put_contents($this->profilesPath . '/' . $name . '.json', $json);
    }

    public function saveProfileArray(string $name, array $data): void
    {
        $profile = Profile::fromArray($data);
        $this->saveProfile($name, $profile);
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
                    'description' => $profile->description ?? 'Sin descripción',
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

    public function scanCustomPlugins(string $path): array
    {
        // Detectar si es archivo .zip
        if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            $slug = pathinfo($path, PATHINFO_FILENAME);
            return [
                $slug => [
                    'slug' => $slug,
                    'name' => $slug,
                    'version' => 'N/A',
                    'description' => 'Plugin desde archivo ZIP',
                    'path' => $path,
                    'source' => 'zip'
                ]
            ];
        }
        
        if (!is_dir($path)) {
            throw new RuntimeException("Path '{$path}' no existe o no es un directorio válido");
        }

        $plugins = [];
        
        // Primero verificar si el path mismo es un plugin
        $slug = basename($path);
        $mainFile = $path . '/' . $slug . '.php';
        
        if (!file_exists($mainFile)) {
            $phpFiles = glob($path . '/*.php');
            $mainFile = !empty($phpFiles) ? $phpFiles[0] : null;
        }
        
        if ($mainFile && file_exists($mainFile)) {
            $headers = $this->getPluginHeaders($mainFile);
            if (!empty($headers['Name'])) {
                return [
                    $slug => [
                        'slug' => $slug,
                        'name' => $headers['Name'],
                        'version' => $headers['Version'] ?? 'N/A',
                        'description' => $headers['Description'] ?? '',
                        'path' => $path
                    ]
                ];
            }
        }
        
        // Si no es plugin, buscar en subdirectorios
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $pluginPath = $path . '/' . $item;
            
            if (!is_dir($pluginPath)) {
                continue;
            }

            $mainFile = $pluginPath . '/' . $item . '.php';
            
            if (!file_exists($mainFile)) {
                $phpFiles = glob($pluginPath . '/*.php');
                $mainFile = !empty($phpFiles) ? $phpFiles[0] : null;
            }

            if (!$mainFile || !file_exists($mainFile)) {
                continue;
            }

            $headers = $this->getPluginHeaders($mainFile);
            
            if (!empty($headers['Name'])) {
                // Validar composer.json si existe
                $composerFile = $pluginPath . '/composer.json';
                if (file_exists($composerFile)) {
                    $composerData = json_decode(file_get_contents($composerFile), true);
                    if (empty($composerData['name'])) {
                        // Omitir plugin sin nombre en composer.json
                        continue;
                    }
                }
                
                $plugins[$item] = [
                    'slug' => $item,
                    'name' => $headers['Name'],
                    'version' => $headers['Version'] ?? 'N/A',
                    'description' => $headers['Description'] ?? '',
                    'path' => $pluginPath
                ];
            }
        }

        return $plugins;
    }

    private function getPluginHeaders(string $file): array
    {
        $content = file_get_contents($file, false, null, 0, 8192);
        $headers = [];

        $fields = [
            'Name' => 'Plugin Name',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author'
        ];

        foreach ($fields as $key => $field) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($field, '/') . ':(.*)$/mi', $content, $match)) {
                $headers[$key] = trim($match[1]);
            }
        }

        return $headers;
    }
    
    public function scanCustomThemes(string $path): array
    {
        // Detectar si es archivo .zip
        if (is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip') {
            $slug = pathinfo($path, PATHINFO_FILENAME);
            return [
                $slug => [
                    'slug' => $slug,
                    'name' => $slug,
                    'version' => 'N/A',
                    'description' => 'Theme desde archivo ZIP',
                    'path' => $path,
                    'source' => 'zip'
                ]
            ];
        }
        
        if (!is_dir($path)) {
            throw new RuntimeException("Path '{$path}' no existe o no es un directorio válido");
        }

        $themes = [];
        
        // Primero verificar si el path mismo es un theme
        $slug = basename($path);
        $styleFile = $path . '/style.css';
        
        if (file_exists($styleFile)) {
            $headers = $this->getThemeHeaders($styleFile);
            if (!empty($headers['Name'])) {
                return [
                    $slug => [
                        'slug' => $slug,
                        'name' => $headers['Name'],
                        'version' => $headers['Version'] ?? 'N/A',
                        'description' => $headers['Description'] ?? '',
                        'path' => $path
                    ]
                ];
            }
        }
        
        // Si no es theme, buscar en subdirectorios
        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $themePath = $path . '/' . $item;
            
            if (!is_dir($themePath)) {
                continue;
            }

            $styleFile = $themePath . '/style.css';
            
            if (!file_exists($styleFile)) {
                continue;
            }

            $headers = $this->getThemeHeaders($styleFile);
            
            if (!empty($headers['Name'])) {
                $themes[$item] = [
                    'slug' => $item,
                    'name' => $headers['Name'],
                    'version' => $headers['Version'] ?? 'N/A',
                    'description' => $headers['Description'] ?? '',
                    'path' => $themePath
                ];
            }
        }

        return $themes;
    }
    
    private function getThemeHeaders(string $file): array
    {
        $content = file_get_contents($file, false, null, 0, 8192);
        $headers = [];

        $fields = [
            'Name' => 'Theme Name',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author'
        ];

        foreach ($fields as $key => $field) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($field, '/') . ':(.*)$/mi', $content, $match)) {
                $headers[$key] = trim($match[1]);
            }
        }

        return $headers;
    }
    
    public function detectDockerMode(string $projectPath = null): bool
    {
        $path = $projectPath ?: getcwd();
        return file_exists($path . '/docker-compose.yml') || 
               file_exists($path . '/docker-compose.yaml') ||
               file_exists($path . '/Dockerfile');
    }
}
