<?php

namespace Roots\BedrockCli\Services;

use RuntimeException;

class PremiumRepoService
{
    private AuthService $authService;
    private string $repoUrl;
    private string $branch;

    public function __construct(string $repoUrl = '', string $branch = 'main')
    {
        $this->authService = new AuthService();
        $this->repoUrl = $repoUrl;
        $this->branch = $branch;
    }

    public function checkAccess(string $repoUrl): array
    {
        $domain = $this->extractDomain($repoUrl);
        $hasAuth = $this->authService->hasAuth($domain);

        if (!$hasAuth) {
            return [
                'success' => false,
                'message' => "No hay credenciales para {$domain}",
                'needs_auth' => true
            ];
        }

        // Hacer ping al repo
        $testUrl = $this->buildApiUrl($repoUrl);
        $result = $this->pingRepository($testUrl, $domain);

        return $result;
    }

    public function scanRepository(string $repoUrl, string $path = 'packages'): array
    {
        $access = $this->checkAccess($repoUrl);
        
        if (!$access['success']) {
            throw new RuntimeException($access['message']);
        }

        $domain = $this->extractDomain($repoUrl);
        $plugins = [];

        // Obtener lista de directorios en packages/
        $contents = $this->getDirectoryContents($repoUrl, $path, $domain);

        foreach ($contents as $item) {
            if ($item['type'] === 'tree') {
                $pluginSlug = $item['name'];
                $versions = $this->getPluginVersions($repoUrl, "{$path}/{$pluginSlug}", $domain);
                
                if (!empty($versions)) {
                    $plugins[] = [
                        'slug' => $pluginSlug,
                        'name' => ucwords(str_replace('-', ' ', $pluginSlug)),
                        'versions' => $versions,
                        'latest' => $versions[0] ?? null
                    ];
                }
            }
        }

        return $plugins;
    }

    private function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }

    private function buildApiUrl(string $repoUrl): string
    {
        $domain = $this->extractDomain($repoUrl);
        
        if (str_contains($domain, 'gitlab')) {
            preg_match('#gitlab\.com[:/](.+?)(?:\.git)?$#', $repoUrl, $matches);
            $projectPath = $matches[1] ?? '';
            $encodedPath = urlencode($projectPath);
            return "https://gitlab.com/api/v4/projects/{$encodedPath}";
        }
        
        if (str_contains($domain, 'github')) {
            preg_match('#github\.com[:/](.+?)/(.+?)(?:\.git)?$#', $repoUrl, $matches);
            $owner = $matches[1] ?? '';
            $repo = $matches[2] ?? '';
            return "https://api.github.com/repos/{$owner}/{$repo}";
        }
        
        if (str_contains($domain, 'bitbucket')) {
            preg_match('#bitbucket\.org[:/](.+?)/(.+?)(?:\.git)?$#', $repoUrl, $matches);
            $workspace = $matches[1] ?? '';
            $repo = $matches[2] ?? '';
            return "https://api.bitbucket.org/2.0/repositories/{$workspace}/{$repo}";
        }

        return '';
    }

    private function pingRepository(string $apiUrl, string $domain): array
    {
        $auth = $this->authService->loadAuthForDomain($domain);
        
        if (!$auth) {
            return [
                'success' => false,
                'message' => "No se encontraron credenciales para {$domain}",
                'needs_auth' => true
            ];
        }

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'bedrock-cli');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        // Headers según el tipo de servicio
        if (str_contains($domain, 'github')) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: token {$auth['token']}",
                "Accept: application/vnd.github.v3+json"
            ]);
        } elseif (str_contains($domain, 'bitbucket')) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$auth['token']}"
            ]);
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$auth['token']}"
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'message' => "Error de conexión: {$curlError}", 'needs_auth' => false];
        }

        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'Acceso verificado', 'needs_auth' => false];
        } elseif ($httpCode === 401 || $httpCode === 403) {
            return ['success' => false, 'message' => 'Token inválido o sin permisos', 'needs_auth' => true];
        } else {
            return ['success' => false, 'message' => "Error HTTP {$httpCode}", 'needs_auth' => false];
        }
    }

    private function getDirectoryContents(string $repoUrl, string $path, string $domain): array
    {
        $auth = $this->authService->loadAuthForDomain($domain);
        
        if (str_contains($domain, 'gitlab')) {
            preg_match('#gitlab\.com[:/](.+?)(?:\.git)?$#', $repoUrl, $matches);
            $projectPath = urlencode($matches[1] ?? '');
            $encodedPath = urlencode($path);
            $apiUrl = "https://gitlab.com/api/v4/projects/{$projectPath}/repository/tree?path={$encodedPath}&ref={$this->branch}";
            $headers = ["Authorization: Bearer {$auth['token']}"];
        } elseif (str_contains($domain, 'github')) {
            preg_match('#github\.com[:/](.+?)/(.+?)(?:\.git)?$#', $repoUrl, $matches);
            $owner = $matches[1] ?? '';
            $repo = $matches[2] ?? '';
            $apiUrl = "https://api.github.com/repos/{$owner}/{$repo}/contents/{$path}?ref={$this->branch}";
            $headers = [
                "Authorization: token {$auth['token']}",
                "Accept: application/vnd.github.v3+json"
            ];
        } elseif (str_contains($domain, 'bitbucket')) {
            preg_match('#bitbucket\.org[:/](.+?)/(.+?)(?:\.git)?$#', $repoUrl, $matches);
            $workspace = $matches[1] ?? '';
            $repo = $matches[2] ?? '';
            $apiUrl = "https://api.bitbucket.org/2.0/repositories/{$workspace}/{$repo}/src/{$this->branch}/{$path}";
            $headers = ["Authorization: Bearer {$auth['token']}"];
        } else {
            return [];
        }

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_USERAGENT, 'bedrock-cli');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true) ?? [];
        
        // Normalizar respuestas de diferentes APIs
        return $this->normalizeDirectoryResponse($data, $domain);
    }

    private function normalizeDirectoryResponse(array $data, string $domain): array
    {
        if (str_contains($domain, 'gitlab')) {
            return $data;
        }
        
        if (str_contains($domain, 'github')) {
            return array_map(fn($item) => [
                'name' => $item['name'],
                'type' => $item['type'] === 'dir' ? 'tree' : 'blob'
            ], $data);
        }
        
        if (str_contains($domain, 'bitbucket')) {
            return array_map(fn($item) => [
                'name' => basename($item['path']),
                'type' => $item['type'] === 'commit_directory' ? 'tree' : 'blob'
            ], $data['values'] ?? []);
        }
        
        return [];
    }

    private function getPluginVersions(string $repoUrl, string $path, string $domain): array
    {
        $contents = $this->getDirectoryContents($repoUrl, $path, $domain);
        $versions = [];

        foreach ($contents as $item) {
            if ($item['type'] === 'tree' && preg_match('/^\d+\.\d+/', $item['name'])) {
                $versions[] = $item['name'];
            }
        }

        usort($versions, 'version_compare');
        return array_reverse($versions);
    }
}
