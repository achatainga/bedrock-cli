<?php

namespace Roots\BedrockCli\Services;

use Symfony\Component\Process\Process;

class StateDetectorService
{
    public function detectProjectState(): array
    {
        return [
            'is_bedrock' => $this->isBedrockProject(),
            'docker_installed' => $this->isDockerInstalled(),
            'docker_running' => $this->isDockerRunning(),
            'wp_installed' => $this->isWordPressInstalled(),
            'acorn_installed' => $this->isAcornInstalled(),
            'acorn_configured' => $this->isAcornConfigured(),
            'env_exists' => file_exists(getcwd() . '/.env'),
            'containers_running' => $this->areContainersRunning(),
            'db_exists' => $this->databaseExists(),
            'db_has_tables' => $this->databaseHasTables(),
            'config' => $this->readConfiguration(),
        ];
    }
    
    public function readConfiguration(): array
    {
        return [
            'env' => $this->readEnvFile(),
            'docker_compose' => $this->readDockerCompose(),
        ];
    }
    
    private function readEnvFile(): array
    {
        $envFile = getcwd() . '/.env';
        
        if (!file_exists($envFile)) {
            return [];
        }
        
        $env = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
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
    
    private function readDockerCompose(): array
    {
        $dockerFile = getcwd() . '/docker-compose.yml';
        
        if (!file_exists($dockerFile)) {
            return [];
        }
        
        $content = file_get_contents($dockerFile);
        $config = [];
        
        // Extraer puerto HTTP de nginx
        if (preg_match('/nginx:.*?ports:.*?"(\d+):80"/s', $content, $matches)) {
            $config['http_port'] = $matches[1];
        }
        
        // Extraer puerto MySQL
        if (preg_match('/mysql:.*?ports:.*?"(\d+):3306"/s', $content, $matches)) {
            $config['mysql_port'] = $matches[1];
        }
        
        // Extraer nombre de BD
        if (preg_match('/MYSQL_DATABASE:\s*([^\s]+)/', $content, $matches)) {
            $config['db_name'] = $matches[1];
        }
        
        // Extraer contraseña de BD
        if (preg_match('/MYSQL_ROOT_PASSWORD:\s*([^\s]+)/', $content, $matches)) {
            $config['db_password'] = $matches[1];
        }
        
        return $config;
    }
    
    private function databaseExists(): bool
    {
        if (!$this->areContainersRunning()) {
            return false;
        }
        
        $env = $this->readEnvFile();
        $dbName = $env['DB_NAME'] ?? 'bedrock';
        $dbUser = $env['DB_USER'] ?? 'root';
        $dbPass = $env['DB_PASSWORD'] ?? 'mysql';
        
        $process = new Process([
            'docker-compose', 'exec', '-T', 'mysql',
            'mysql', "-u{$dbUser}", "-p{$dbPass}",
            '-e', "SHOW DATABASES LIKE '{$dbName}';"
        ]);
        $process->run();
        
        return $process->isSuccessful() && str_contains($process->getOutput(), $dbName);
    }
    
    private function databaseHasTables(): bool
    {
        if (!$this->databaseExists()) {
            return false;
        }
        
        $env = $this->readEnvFile();
        $dbName = $env['DB_NAME'] ?? 'bedrock';
        $dbUser = $env['DB_USER'] ?? 'root';
        $dbPass = $env['DB_PASSWORD'] ?? 'mysql';
        
        $process = new Process([
            'docker-compose', 'exec', '-T', 'mysql',
            'mysql', "-u{$dbUser}", "-p{$dbPass}", $dbName,
            '-e', 'SHOW TABLES;'
        ]);
        $process->run();
        
        $output = $process->getOutput();
        return $process->isSuccessful() && !empty(trim($output)) && str_contains($output, 'Tables_in_');
    }
    
    private function isBedrockProject(): bool
    {
        $composerFile = getcwd() . '/composer.json';
        
        if (!file_exists($composerFile)) {
            return false;
        }
        
        $content = file_get_contents($composerFile);
        return str_contains($content, 'roots/bedrock') || str_contains($content, 'roots/wordpress');
    }
    
    private function isDockerInstalled(): bool
    {
        $process = Process::fromShellCommandline('docker --version');
        $process->run();
        
        return $process->isSuccessful();
    }
    
    private function isDockerRunning(): bool
    {
        $process = Process::fromShellCommandline('docker info');
        $process->run();
        
        return $process->isSuccessful();
    }
    
    private function isWordPressInstalled(): bool
    {
        if (!$this->areContainersRunning()) {
            return false;
        }
        
        $process = new Process(['docker-compose', 'exec', '-T', 'web', 'wp', 'core', 'is-installed']);
        $process->run();
        
        return $process->isSuccessful();
    }
    
    private function isAcornInstalled(): bool
    {
        $composerFile = getcwd() . '/composer.json';
        
        if (!file_exists($composerFile)) {
            return false;
        }
        
        $composerJson = json_decode(file_get_contents($composerFile), true);
        
        return isset($composerJson['require']['roots/acorn']);
    }
    
    private function isAcornConfigured(): bool
    {
        $storageExists = is_dir(getcwd() . '/storage/framework');
        $configExists = is_dir(getcwd() . '/config');
        $bootExists = file_exists(getcwd() . '/web/app/mu-plugins/acorn-boot.php');
        
        return $storageExists && $configExists && $bootExists;
    }
    
    private function areContainersRunning(): bool
    {
        $process = Process::fromShellCommandline('docker-compose ps --services --filter "status=running"');
        $process->run();
        
        if (!$process->isSuccessful()) {
            return false;
        }
        
        $output = trim($process->getOutput());
        return !empty($output);
    }
}
