<?php

namespace Roots\BedrockCli\Services;

use RuntimeException;
use ZipArchive;

class PremiumCacheService
{
    private string $cachePath;
    private AuthService $authService;

    public function __construct()
    {
        $home = $this->getHomeDirectory();
        $this->cachePath = $home . '/.bedrock-cli/cache/premium';
        $this->authService = new AuthService();
        $this->ensureCacheDirectory();
    }

    private function getHomeDirectory(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return getenv('USERPROFILE') ?: getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }
        return getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'];
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function downloadPlugin(string $repoUrl, string $name, string $version, string $repoPath): string
    {
        $pluginDir = $this->cachePath . "/{$name}/{$version}";
        $zipPath = $pluginDir . "/{$name}.zip";

        // Si ya existe, retornar path
        if (file_exists($zipPath)) {
            return $zipPath;
        }

        // Crear directorio
        if (!is_dir($pluginDir)) {
            mkdir($pluginDir, 0755, true);
        }

        // Descargar desde GitLab API
        $domain = $this->extractDomain($repoUrl);
        $fileUrl = $this->buildFileUrl($repoUrl, $repoPath . $name . '.zip', $domain);
        
        $auth = $this->authService->loadAuthForDomain($domain);
        if (!$auth) {
            throw new RuntimeException("No hay credenciales para {$domain}");
        }

        $ch = curl_init($fileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'bedrock-cli');
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        if (str_contains($domain, 'gitlab')) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$auth['token']}"
            ]);
        }

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $content === false) {
            throw new RuntimeException("Error descargando plugin: HTTP {$httpCode}");
        }

        file_put_contents($zipPath, $content);
        return $zipPath;
    }

    public function extractPlugin(string $name, string $version): string
    {
        $pluginDir = $this->cachePath . "/{$name}/{$version}";
        $zipPath = $pluginDir . "/{$name}.zip";
        $extractPath = $pluginDir . "/extracted";

        // Si ya está extraído, retornar
        if (is_dir($extractPath)) {
            return $extractPath;
        }

        if (!file_exists($zipPath)) {
            throw new RuntimeException("Plugin {$name} v{$version} no está en caché");
        }

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("Extensión ZIP no disponible en PHP");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("No se pudo abrir {$zipPath}");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        return $extractPath;
    }

    public function ensureComposerJson(string $name, string $version, string $vendor = 'cached'): void
    {
        $extractPath = $this->cachePath . "/{$name}/{$version}/extracted";
        
        // Buscar si hay subdirectorio con el nombre del plugin
        $pluginPath = $extractPath;
        if (is_dir($extractPath . '/' . $name)) {
            $pluginPath = $extractPath . '/' . $name;
        }

        $composerPath = $pluginPath . '/composer.json';

        // Si ya existe, no hacer nada
        if (file_exists($composerPath)) {
            return;
        }

        // Generar composer.json
        $composerData = [
            'name' => "{$vendor}/{$name}",
            'version' => $version,
            'type' => 'wordpress-plugin',
            'description' => "Premium plugin {$name}",
            'require' => [
                'composer/installers' => '^2.0'
            ]
        ];

        file_put_contents($composerPath, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function getCachePath(string $name, string $version): string
    {
        $extractPath = $this->cachePath . "/{$name}/{$version}/extracted";
        
        // Si hay subdirectorio con el nombre del plugin, usar ese
        if (is_dir($extractPath . '/' . $name)) {
            return $extractPath . '/' . $name;
        }
        
        return $extractPath;
    }

    public function pluginExists(string $name, string $version): bool
    {
        $pluginDir = $this->cachePath . "/{$name}/{$version}";
        return is_dir($pluginDir);
    }

    public function clearCache(): void
    {
        if (is_dir($this->cachePath)) {
            $this->deleteDirectory($this->cachePath);
            mkdir($this->cachePath, 0755, true);
        }
    }

    public function clearPluginCache(string $name, string $version): void
    {
        $pluginDir = $this->cachePath . "/{$name}/{$version}";
        if (is_dir($pluginDir)) {
            $this->deleteDirectory($pluginDir);
        }
    }

    public function clearPluginComposerJson(string $name, string $version): void
    {
        $extractPath = $this->cachePath . "/{$name}/{$version}/extracted";
        $pluginPath = is_dir($extractPath . '/' . $name) ? $extractPath . '/' . $name : $extractPath;
        $composerPath = $pluginPath . '/composer.json';
        
        if (file_exists($composerPath)) {
            unlink($composerPath);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }

    private function buildFileUrl(string $repoUrl, string $filePath, string $domain): string
    {
        if (str_contains($domain, 'gitlab')) {
            preg_match('#gitlab\.com[:/](.+?)(?:\.git)?$#', $repoUrl, $matches);
            $projectPath = urlencode($matches[1] ?? '');
            $encodedPath = urlencode($filePath);
            return "https://gitlab.com/api/v4/projects/{$projectPath}/repository/files/{$encodedPath}/raw?ref=develop";
        }
        
        throw new RuntimeException("Solo GitLab soportado por ahora");
    }
}
