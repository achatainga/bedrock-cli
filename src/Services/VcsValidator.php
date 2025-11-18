<?php

namespace Roots\BedrockCli\Services;

class VcsValidator
{
    private const BRANCHES = ['develop', 'main', 'master'];
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function getPackageInfo(string $url, ?string $specifiedBranch = null): ?array
    {
        $parsed = $this->parseVcsUrl($url);
        if (!$parsed) {
            return null;
        }

        // Si se especifica rama, solo intentar esa
        $branches = $specifiedBranch ? [$specifiedBranch] : self::BRANCHES;

        foreach ($branches as $branch) {
            $composerUrl = $this->buildRawUrl($parsed, $branch);
            $content = $this->fetchUrl($composerUrl, $parsed['host']);
            
            if ($content !== false) {
                $composer = json_decode($content, true);
                if (isset($composer['name'])) {
                    return [
                        'name' => $composer['name'],
                        'branch' => $branch
                    ];
                }
            }
        }

        return null;
    }

    private function parseVcsUrl(string $url): ?array
    {
        // https://gitlab.com/detodo24dev/dt24-registro.git
        // https://github.com/owner/repo.git
        
        $pattern = '#^https?://([^/]+)/([^/]+)/([^/]+?)(?:\.git)?$#';
        if (!preg_match($pattern, $url, $matches)) {
            return null;
        }

        return [
            'host' => $matches[1],
            'owner' => $matches[2],
            'repo' => $matches[3]
        ];
    }

    private function buildRawUrl(array $parsed, string $branch): string
    {
        $host = $parsed['host'];
        $owner = $parsed['owner'];
        $repo = $parsed['repo'];

        // GitLab API: https://gitlab.com/api/v4/projects/:id/repository/files/composer.json/raw?ref=branch
        if (str_contains($host, 'gitlab')) {
            $projectPath = urlencode("{$owner}/{$repo}");
            $filePath = urlencode('composer.json');
            return "https://{$host}/api/v4/projects/{$projectPath}/repository/files/{$filePath}/raw?ref={$branch}";
        }

        // GitHub: https://raw.githubusercontent.com/owner/repo/branch/composer.json
        if (str_contains($host, 'github')) {
            return "https://raw.githubusercontent.com/{$owner}/{$repo}/{$branch}/composer.json";
        }

        return '';
    }

    private function fetchUrl(string $url, string $host): string|false
    {
        $auth = $this->authService->loadAuthForDomain($host);
        
        if ($auth) {
            $headers = [];
            
            if ($auth['type'] === 'gitlab-oauth') {
                $headers[] = "PRIVATE-TOKEN: {$auth['token']}";
            } elseif ($auth['type'] === 'github-oauth') {
                $headers[] = "Authorization: token {$auth['token']}";
            } elseif ($auth['type'] === 'http-basic') {
                $encoded = base64_encode("{$auth['credentials']['username']}:{$auth['credentials']['password']}");
                $headers[] = "Authorization: Basic {$encoded}";
            }
            
            if (!empty($headers)) {
                $context = stream_context_create([
                    'http' => ['header' => implode("\r\n", $headers) . "\r\n"]
                ]);
                return @file_get_contents($url, false, $context);
            }
        }
        
        // Sin autenticación (repos públicos)
        return @file_get_contents($url);
    }
}
