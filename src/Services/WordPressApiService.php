<?php

namespace Roots\BedrockCli\Services;

class WordPressApiService
{
    private const API_BASE = 'https://api.wordpress.org';
    private const CACHE_DIR = '.bedrock-cli/cache';
    private const CACHE_TTL = 3600; // 1 hour

    private string $cacheDir;

    public function __construct()
    {
        $home = $this->getHomeDirectory();
        $this->cacheDir = $home . DIRECTORY_SEPARATOR . self::CACHE_DIR;
        $this->ensureCacheDirectory();
    }

    public function searchPlugins(string $query, int $page = 1, int $perPage = 10): array
    {
        $cacheKey = "plugins_search_{$query}_{$page}_{$perPage}";
        
        if ($cached = $this->getCache($cacheKey)) {
            return $cached;
        }

        $url = self::API_BASE . '/plugins/info/1.2/?action=query_plugins&request[search]=' . urlencode($query) 
            . '&request[page]=' . $page . '&request[per_page]=' . $perPage;

        $response = $this->makeRequest($url);
        
        if (!$response) {
            return ['plugins' => [], 'info' => ['results' => 0]];
        }

        $this->setCache($cacheKey, $response);
        return $response;
    }

    public function getPluginInfo(string $slug): ?array
    {
        $cacheKey = "plugin_info_{$slug}";
        
        if ($cached = $this->getCache($cacheKey)) {
            return $cached;
        }

        $url = self::API_BASE . '/plugins/info/1.2/?action=plugin_information&request[slug]=' . urlencode($slug);
        
        $response = $this->makeRequest($url);
        
        if ($response) {
            $this->setCache($cacheKey, $response);
        }
        
        return $response;
    }

    public function searchThemes(string $query, int $page = 1, int $perPage = 10): array
    {
        $cacheKey = "themes_search_{$query}_{$page}_{$perPage}";
        
        if ($cached = $this->getCache($cacheKey)) {
            return $cached;
        }

        $url = self::API_BASE . '/themes/info/1.2/?action=query_themes&request[search]=' . urlencode($query)
            . '&request[page]=' . $page . '&request[per_page]=' . $perPage;

        $response = $this->makeRequest($url);
        
        if (!$response) {
            return ['themes' => [], 'info' => ['results' => 0]];
        }

        $this->setCache($cacheKey, $response);
        return $response;
    }

    public function getThemeInfo(string $slug): ?array
    {
        $cacheKey = "theme_info_{$slug}";
        
        if ($cached = $this->getCache($cacheKey)) {
            return $cached;
        }

        $url = self::API_BASE . '/themes/info/1.2/?action=theme_information&request[slug]=' . urlencode($slug);
        
        $response = $this->makeRequest($url);
        
        if ($response) {
            $this->setCache($cacheKey, $response);
        }
        
        return $response;
    }

    private function makeRequest(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'User-Agent: bedrock-cli/1.0',
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return null;
        }

        return json_decode($response, true);
    }

    private function getHomeDirectory(): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return getenv('USERPROFILE') ?: getenv('HOMEDRIVE') . getenv('HOMEPATH');
        }

        $home = getenv('HOME');
        if (!$home && function_exists('posix_getpwuid') && function_exists('posix_getuid')) {
            $home = posix_getpwuid(posix_getuid())['dir'] ?? null;
        }

        return $home ?: sys_get_temp_dir();
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    private function getCache(string $key): ?array
    {
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.json';
        
        if (!file_exists($file)) {
            return null;
        }

        if (time() - filemtime($file) > self::CACHE_TTL) {
            unlink($file);
            return null;
        }

        $content = file_get_contents($file);
        return json_decode($content, true);
    }

    private function setCache(string $key, array $data): void
    {
        $file = $this->cacheDir . DIRECTORY_SEPARATOR . md5($key) . '.json';
        file_put_contents($file, json_encode($data));
    }
}
