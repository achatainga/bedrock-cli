<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Process\Process;

class ProjectDiagnosticService
{
    public function generateDiagnosticReport(): array
    {
        return [
            'system' => $this->getSystemInfo(),
            'docker' => $this->getDockerInfo(),
            'project' => $this->getProjectInfo(),
            'wordpress' => $this->getWordPressInfo(),
            'database' => $this->getDatabaseInfo(),
            'plugins' => $this->getPluginsInfo(),
            'themes' => $this->getThemesInfo(),
        ];
    }

    private function getSystemInfo(): array
    {
        return [
            'os' => PHP_OS,
            'php_version' => PHP_VERSION,
            'cwd' => getcwd(),
        ];
    }

    private function getDockerInfo(): array
    {
        $info = ['installed' => false, 'running' => false, 'containers' => []];

        $process = Process::fromShellCommandline('docker --version');
        $process->run();
        $info['installed'] = $process->isSuccessful();

        if (!$info['installed']) {
            return $info;
        }

        $process = Process::fromShellCommandline('docker info');
        $process->run();
        $info['running'] = $process->isSuccessful();

        if ($info['running']) {
            $process = new Process(['docker-compose', 'ps', '--format', 'json']);
            $process->run();
            
            if ($process->isSuccessful()) {
                $output = trim($process->getOutput());
                if (!empty($output)) {
                    $lines = explode("\n", $output);
                    foreach ($lines as $line) {
                        $container = json_decode($line, true);
                        if ($container) {
                            $info['containers'][] = [
                                'name' => $container['Name'] ?? $container['Service'] ?? 'unknown',
                                'state' => $container['State'] ?? 'unknown',
                                'status' => $container['Status'] ?? 'unknown',
                            ];
                        }
                    }
                }
            }
        }

        return $info;
    }

    private function getProjectInfo(): array
    {
        $info = ['is_bedrock' => false, 'env_exists' => false, 'config' => []];

        $composerFile = getcwd() . '/composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            $info['is_bedrock'] = isset($composer['require']['roots/wordpress']) || 
                                  isset($composer['require']['roots/bedrock']);
        }

        $envFile = getcwd() . '/.env';
        $info['env_exists'] = file_exists($envFile);

        if ($info['env_exists']) {
            $env = $this->parseEnvFile($envFile);
            $info['config'] = [
                'url' => $env['WP_HOME'] ?? null,
                'db_name' => $env['DB_NAME'] ?? null,
                'db_host' => $env['DB_HOST'] ?? null,
            ];
        }

        return $info;
    }

    private function getWordPressInfo(): array
    {
        $info = ['installed' => false, 'version' => null, 'url' => null];

        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'core', 'is-installed']);
        $process->run();
        $info['installed'] = $process->isSuccessful();

        if ($info['installed']) {
            $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'core', 'version']);
            $process->run();
            if ($process->isSuccessful()) {
                $info['version'] = trim($process->getOutput());
            }

            $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'option', 'get', 'siteurl']);
            $process->run();
            if ($process->isSuccessful()) {
                $info['url'] = trim($process->getOutput());
            }
        }

        return $info;
    }

    private function getDatabaseInfo(): array
    {
        $info = ['accessible' => false, 'tables_count' => 0];

        $env = $this->parseEnvFile(getcwd() . '/.env');
        $dbName = $env['DB_NAME'] ?? 'bedrock';
        $dbUser = $env['DB_USER'] ?? 'root';
        $dbPass = $env['DB_PASSWORD'] ?? 'mysql';

        $process = new Process([
            'docker-compose', 'exec', '-T', 'mysql',
            'mysql', "-u{$dbUser}", "-p{$dbPass}", $dbName,
            '-e', 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();'
        ]);
        $process->run();

        if ($process->isSuccessful()) {
            $info['accessible'] = true;
            $output = trim($process->getOutput());
            if (preg_match('/(\d+)/', $output, $matches)) {
                $info['tables_count'] = (int)$matches[1];
            }
        }

        return $info;
    }

    private function getPluginsInfo(): array
    {
        $plugins = ['active' => [], 'inactive' => []];

        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'plugin', 'list', '--format=json']);
        $process->run();

        if ($process->isSuccessful()) {
            $data = json_decode($process->getOutput(), true);
            if ($data) {
                foreach ($data as $plugin) {
                    $key = $plugin['status'] === 'active' ? 'active' : 'inactive';
                    $plugins[$key][] = [
                        'name' => $plugin['name'],
                        'version' => $plugin['version'] ?? null,
                    ];
                }
            }
        }

        return $plugins;
    }

    private function getThemesInfo(): array
    {
        $themes = ['active' => null, 'available' => []];

        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'theme', 'list', '--format=json']);
        $process->run();

        if ($process->isSuccessful()) {
            $data = json_decode($process->getOutput(), true);
            if ($data) {
                foreach ($data as $theme) {
                    if ($theme['status'] === 'active') {
                        $themes['active'] = [
                            'name' => $theme['name'],
                            'version' => $theme['version'] ?? null,
                        ];
                    }
                    $themes['available'][] = $theme['name'];
                }
            }
        }

        return $themes;
    }

    private function parseEnvFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $env = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $env[$key] = trim($value, "'\"");
            }
        }

        return $env;
    }
}
