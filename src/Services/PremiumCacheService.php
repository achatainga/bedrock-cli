<?php

namespace Roots\BedrockCli\Services;

use RuntimeException;
use ZipArchive;
use Symfony\Component\Filesystem\Filesystem;

class PremiumCacheService
{
    private string $cachePath;
    private string $themeCachePath;
    private AuthService $authService;

    public function __construct()
    {
        $home = $this->getHomeDirectory();
        $this->cachePath = $home . '/.bedrock-cli/cache/premium';
        $this->themeCachePath = $home . '/.bedrock-cli/cache/themes';
        $this->authService = new AuthService();
        $this->ensureCacheDirectory();
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
        $newPath = $this->cachePath . "/plugins/{$name}/{$version}/extracted";
        if (is_dir($newPath)) {
            if (is_dir($newPath . '/' . $name)) {
                return $newPath . '/' . $name;
            }
            return $newPath;
        }
        
        $legacyPath = $this->cachePath . "/{$name}/{$version}/extracted";
        if (is_dir($legacyPath . '/' . $name)) {
            return $legacyPath . '/' . $name;
        }
        return $legacyPath;
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

    public function extractMetadataFromZip(string $zipPath, string $type): array
    {
        if (!file_exists($zipPath)) {
            throw new RuntimeException("File not found: {$zipPath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Cannot open zip file: {$zipPath}");
        }

        $name = null;
        $version = null;
        $source = null;

        $composerJson = $this->findFileInZip($zip, 'composer.json');
        if ($composerJson) {
            $data = json_decode($composerJson, true);
            if (isset($data['version'])) {
                $version = $data['version'];
                $source = 'composer.json';
            }
            if (isset($data['name'])) {
                $parts = explode('/', $data['name']);
                $name = $this->sanitizeSlug(end($parts));
            }
        }

        if (!$version) {
            if ($type === 'theme') {
                $styleCss = $this->findFileInZip($zip, 'style.css');
                if ($styleCss) {
                    if (preg_match('/Theme Name:\s*(.+)/i', $styleCss, $m)) {
                        $name = $name ?? $this->sanitizeSlug(trim($m[1]));
                    }
                    if (preg_match('/Version:\s*([\d\.]+)/i', $styleCss, $m)) {
                        $version = trim($m[1]);
                        $source = 'style.css';
                    }
                }
            }

            if ($type === 'plugin') {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename = $zip->getNameIndex($i);
                    if (preg_match('/\.php$/', $filename) && !str_contains($filename, '/vendor/')) {
                        $content = $zip->getFromIndex($i);
                        if (preg_match('/Plugin Name:\s*(.+)/i', $content, $m)) {
                            $name = $name ?? $this->sanitizeSlug(trim($m[1]));
                        }
                        if (preg_match('/Version:\s*([\d\.]+)/i', $content, $m)) {
                            $version = trim($m[1]);
                            $source = basename($filename);
                            break;
                        }
                    }
                }
            }
        }

        $zip->close();

        return [
            'name' => $name,
            'version' => $version,
            'source' => $source
        ];
    }

    private function findFileInZip(ZipArchive $zip, string $filename): ?string
    {
        $content = $zip->getFromName($filename);
        if ($content !== false) {
            return $content;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with($name, '/' . $filename)) {
                return $zip->getFromIndex($i);
            }
        }

        return null;
    }

    private function sanitizeSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    public function importToCache(string $zipPath, string $name, string $version, string $type): bool
    {
        if (!file_exists($zipPath)) {
            throw new RuntimeException("File not found: {$zipPath}");
        }

        $typeDir = $type === 'plugin' ? 'plugins' : 'themes';
        $targetDir = $this->cachePath . "/{$typeDir}/{$name}/{$version}";
        $targetZip = $targetDir . "/{$name}.zip";
        $extractPath = $targetDir . "/extracted";

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        copy($zipPath, $targetZip);

        $zip = new ZipArchive();
        if ($zip->open($targetZip) !== true) {
            throw new RuntimeException("Cannot open zip file: {$targetZip}");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $pluginPath = $extractPath;
        if (is_dir($extractPath . '/' . $name)) {
            $pluginPath = $extractPath . '/' . $name;
        }

        $composerPath = $pluginPath . '/composer.json';
        if (!file_exists($composerPath)) {
            $composerData = [
                'name' => "cached/{$name}",
                'version' => $version,
                'type' => $type === 'plugin' ? 'wordpress-plugin' : 'wordpress-theme',
                'description' => "Premium {$type} {$name}",
                'require' => [
                    'composer/installers' => '^2.0'
                ]
            ];
            file_put_contents($composerPath, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return true;
    }

    public function updateCacheVersion(string $name, string $oldVersion, string $newVersion): ?string
    {
        $type = $this->detectCacheType($name, $oldVersion);
        if (!$type) {
            throw new RuntimeException("No se encontró {$name} {$oldVersion} en cache");
        }

        $typeDir = $type === 'plugin' ? 'plugins' : 'themes';
        $oldPath = $this->cachePath . "/{$typeDir}/{$name}/{$oldVersion}";
        $newPath = $this->cachePath . "/{$typeDir}/{$name}/{$newVersion}";

        if (!is_dir($oldPath)) {
            throw new RuntimeException("No existe {$name} {$oldVersion} en cache");
        }

        if (is_dir($newPath)) {
            throw new RuntimeException("Ya existe {$name} {$newVersion} en cache");
        }

        rename($oldPath, $newPath);

        $extractPath = $newPath . "/extracted";
        $pluginPath = is_dir($extractPath . '/' . $name) ? $extractPath . '/' . $name : $extractPath;
        $composerPath = $pluginPath . '/composer.json';

        if (file_exists($composerPath)) {
            $composerData = json_decode(file_get_contents($composerPath), true);
            $composerData['version'] = $newVersion;
            file_put_contents($composerPath, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return $type;
    }

    private function detectCacheType(string $name, string $version): ?string
    {
        if (is_dir($this->cachePath . "/plugins/{$name}/{$version}")) {
            return 'plugin';
        }
        if (is_dir($this->cachePath . "/themes/{$name}/{$version}")) {
            return 'theme';
        }
        return null;
    }

    public function extractTheme(string $name, string $version): string
    {
        $themeDir = $this->themeCachePath . "/{$name}/{$version}";
        $zipPath = $themeDir . "/{$name}.zip";
        $extractPath = $themeDir . "/extracted";

        if (is_dir($extractPath)) {
            return $extractPath;
        }

        if (!file_exists($zipPath)) {
            throw new RuntimeException("Theme {$name} v{$version} no está en caché");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("No se pudo abrir {$zipPath}");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        return $extractPath;
    }

    public function clearThemeComposerJson(string $name, string $version): void
    {
        $extractPath = $this->themeCachePath . "/{$name}/{$version}/extracted";
        $themePath = is_dir($extractPath . '/' . $name) ? $extractPath . '/' . $name : $extractPath;
        $composerPath = $themePath . '/composer.json';
        
        if (file_exists($composerPath)) {
            unlink($composerPath);
        }
    }

    public function ensureThemeComposerJson(string $name, string $version, string $vendor = 'cached'): void
    {
        $extractPath = $this->themeCachePath . "/{$name}/{$version}/extracted";
        $themePath = is_dir($extractPath . '/' . $name) ? $extractPath . '/' . $name : $extractPath;
        $composerPath = $themePath . '/composer.json';

        if (file_exists($composerPath)) {
            return;
        }

        $composerData = [
            'name' => "{$vendor}/{$name}",
            'version' => $version,
            'type' => 'wordpress-theme',
            'description' => "Premium theme {$name}",
            'require' => [
                'composer/installers' => '^2.0'
            ]
        ];

        file_put_contents($composerPath, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function getThemeCachePath(string $name, string $version): string
    {
        $extractPath = $this->themeCachePath . "/{$name}/{$version}/extracted";
        
        // Check if extracted directory exists
        if (!is_dir($extractPath)) {
            return $extractPath;
        }
        
        // First check for exact name match
        if (is_dir($extractPath . '/' . $name)) {
            return $extractPath . '/' . $name;
        }
        
        // Then check for slug (e.g., motta-theme -> motta)
        $slug = str_replace('-theme', '', $name);
        if ($slug !== $name && is_dir($extractPath . '/' . $slug)) {
            return $extractPath . '/' . $slug;
        }
        
        // Return first subdirectory if exists
        $dirs = array_diff(scandir($extractPath), ['.', '..']);
        foreach ($dirs as $dir) {
            if (is_dir($extractPath . '/' . $dir)) {
                return $extractPath . '/' . $dir;
            }
        }
        
        return $extractPath;
    }

    public function themeExists(string $name, string $version): bool
    {
        $themeDir = $this->themeCachePath . "/{$name}/{$version}";
        return is_dir($themeDir);
    }

    public function downloadTheme(string $repoUrl, string $name, string $version, string $repoPath): string
    {
        $themeDir = $this->themeCachePath . "/{$name}/{$version}";
        
        // Extract actual ZIP filename from repoPath (e.g., packages/motta-theme/1.5.4/ contains motta.zip)
        // Try to find the ZIP file in the directory
        $zipFilename = $name . '.zip'; // Default to name
        
        // Check if there's a different ZIP file in the path
        $domain = parse_url($repoUrl, PHP_URL_HOST);
        if (str_contains($domain, 'gitlab')) {
            preg_match('#gitlab\.com[:/](.+?)(?:\.git)?$#', $repoUrl, $matches);
            $projectPath = urlencode($matches[1] ?? '');
            $encodedPath = urlencode(rtrim($repoPath, '/'));
            
            // Try to list files in the directory to find the actual ZIP name
            $auth = $this->authService->loadAuthForDomain($domain);
            if ($auth) {
                $listUrl = "https://gitlab.com/api/v4/projects/{$projectPath}/repository/tree?path={$encodedPath}&ref=develop";
                $ch = curl_init($listUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$auth['token']}"]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $listContent = curl_exec($ch);
                curl_close($ch);
                
                if ($listContent) {
                    $files = json_decode($listContent, true);
                    foreach ($files as $file) {
                        if (isset($file['name']) && str_ends_with($file['name'], '.zip')) {
                            $zipFilename = $file['name'];
                            break;
                        }
                    }
                }
            }
        }
        
        $zipPath = $themeDir . "/{$zipFilename}";

        if (file_exists($zipPath)) {
            return $zipPath;
        }

        if (!is_dir($themeDir)) {
            mkdir($themeDir, 0755, true);
        }

        $auth = $this->authService->loadAuthForDomain($domain);
        
        if (!$auth) {
            throw new \RuntimeException("No hay credenciales para {$domain}");
        }

        // Download the specific ZIP file
        $fileUrl = $this->buildFileUrl($repoUrl, $repoPath . $zipFilename, $domain);

        $ch = curl_init($fileUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$auth['token']}"]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $content === false || empty($content)) {
            throw new \RuntimeException("Error descargando theme {$zipFilename}: HTTP {$httpCode}");
        }

        file_put_contents($zipPath, $content);
        return $zipPath;
    }

    public function clearThemeCache(string $name, string $version): void
    {
        $themeDir = $this->themeCachePath . "/{$name}/{$version}";
        if (is_dir($themeDir)) {
            $this->deleteDirectory($themeDir);
        }
    }
}

