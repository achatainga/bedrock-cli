<?php

namespace Roots\BedrockCli\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class AuthService
{
    private string $globalAuthFile;
    private string $homeDir;

    public function __construct()
    {
        $this->homeDir = $this->getComposerHome();
        $this->globalAuthFile = $this->homeDir . DIRECTORY_SEPARATOR . 'auth.json';
    }

    public function addAuth(string $type, string $domain, array $credentials): void
    {
        $auth = $this->loadAuth();

        switch ($type) {
            case 'gitlab':
            case 'github':
            case 'bitbucket':
                $auth[$type . '-oauth'][$domain] = $credentials['token'];
                break;
            case 'http-basic':
                $auth['http-basic'][$domain] = [
                    'username' => $credentials['username'],
                    'password' => $credentials['password']
                ];
                break;
            default:
                throw new RuntimeException("Tipo de autenticación no soportado: {$type}");
        }

        $this->saveAuth($auth);
    }

    public function removeAuth(string $type, string $domain): void
    {
        $auth = $this->loadAuth();
        $key = $type === 'http-basic' ? 'http-basic' : $type . '-oauth';

        if (isset($auth[$key][$domain])) {
            unset($auth[$key][$domain]);
            
            if (empty($auth[$key])) {
                unset($auth[$key]);
            }
            
            $this->saveAuth($auth);
        }
    }

    public function listAuth(): array
    {
        $auth = $this->loadAuth();
        $list = [];

        foreach (['gitlab-oauth', 'github-oauth', 'bitbucket-oauth', 'http-basic'] as $type) {
            if (isset($auth[$type])) {
                foreach ($auth[$type] as $domain => $credentials) {
                    $list[] = [
                        'type' => str_replace('-oauth', '', $type),
                        'domain' => $domain,
                        'configured' => true
                    ];
                }
            }
        }

        return $list;
    }

    public function hasAuth(string $domain): bool
    {
        $auth = $this->loadAuth();
        
        foreach (['gitlab-oauth', 'github-oauth', 'bitbucket-oauth', 'http-basic'] as $type) {
            if (isset($auth[$type][$domain])) {
                return true;
            }
        }
        
        return false;
    }

    private function loadAuth(): array
    {
        if (!file_exists($this->globalAuthFile)) {
            return [];
        }

        $content = file_get_contents($this->globalAuthFile);
        $auth = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('auth.json contiene JSON inválido');
        }

        return $auth ?? [];
    }

    private function saveAuth(array $auth): void
    {
        $composerDir = dirname($this->globalAuthFile);
        
        if (!is_dir($composerDir)) {
            mkdir($composerDir, 0755, true);
        }

        $json = json_encode($auth, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->globalAuthFile, $json);
        chmod($this->globalAuthFile, 0600); // Solo lectura/escritura para el propietario
    }

    private function getComposerHome(): string
    {
        $process = new Process(['composer', 'config', '--global', 'home']);
        $process->setTimeout(10);
        $process->run();
        
        if ($process->isSuccessful() && !empty(trim($process->getOutput()))) {
            $path = trim($process->getOutput());
            // Normalizar separadores de directorio
            return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        }
        
        // Fallback a detección manual
        if (PHP_OS_FAMILY === 'Windows') {
            return getenv('APPDATA') . DIRECTORY_SEPARATOR . 'Composer';
        }
        return (getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir']) . DIRECTORY_SEPARATOR . '.composer';
    }

    public function getAuthFile(): string
    {
        return $this->globalAuthFile;
    }

    public function loadAuthForDomain(string $domain): ?array
    {
        $auth = $this->loadAuth();
        
        foreach (['gitlab-oauth', 'github-oauth', 'bitbucket-oauth'] as $type) {
            if (isset($auth[$type][$domain])) {
                return ['type' => $type, 'token' => $auth[$type][$domain]];
            }
        }
        
        if (isset($auth['http-basic'][$domain])) {
            return ['type' => 'http-basic', 'credentials' => $auth['http-basic'][$domain]];
        }
        
        return null;
    }
}
