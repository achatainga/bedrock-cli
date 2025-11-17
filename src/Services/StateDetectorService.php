<?php

namespace Roots\BedrockCli\Services;

use Roots\BedrockCli\Services\ProjectValidationService;
use Symfony\Component\Process\Process;

class StateDetectorService
{
    private ProjectValidationService $validationService;
    
    public function __construct()
    {
        $this->validationService = new ProjectValidationService();
    }
    public function detectProjectState(): array
    {
        return [
            'is_bedrock' => $this->isBedrockProject(),
            'is_local_install' => $this->isLocalInstall(),
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
            'inconsistencies' => $this->detectInconsistencies(),
            'pending_tasks' => $this->detectPendingTasks(),
        ];
    }
    
    public function isLocalInstall(): bool
    {
        // Local install = bedrock-cli está en require-dev del proyecto
        $composerFile = getcwd() . '/composer.json';
        
        if (!file_exists($composerFile)) {
            return false;
        }
        
        $composer = json_decode(file_get_contents($composerFile), true);
        return isset($composer['require-dev']['achatainga/bedrock-cli']) || 
               isset($composer['require-dev']['roots/bedrock-cli']);
    }
    
    public function detectInconsistencies(): array
    {
        return $this->validationService->detectInconsistencies(getcwd());
    }
    
    public function detectPendingTasks(): array
    {
        $tasks = [];
        $state = [];
        
        // Detectar estado básico sin recursión
        $state['docker_running'] = $this->isDockerRunning();
        $state['containers_running'] = $this->areContainersRunning();
        $state['db_exists'] = $state['containers_running'] ? $this->databaseExists() : false;
        $state['wp_installed'] = $state['containers_running'] ? $this->isWordPressInstalled() : false;
        $state['acorn_installed'] = $this->isAcornInstalled();
        $state['acorn_configured'] = $this->isAcornConfigured();
        
        // Tarea 1: Iniciar Docker
        if (!$state['docker_running']) {
            $tasks[] = [
                'name' => 'Iniciar Docker Desktop',
                'command' => 'bedrock doctor',
                'priority' => 1,
                'severity' => 'critical',
            ];
        }
        
        // Tarea 2: Levantar contenedores
        if ($state['docker_running'] && !$state['containers_running']) {
            $tasks[] = [
                'name' => 'Levantar contenedores',
                'command' => 'docker-compose up -d',
                'priority' => 2,
                'severity' => 'critical',
            ];
        }
        
        // Tarea 3: Crear base de datos
        if ($state['containers_running'] && !$state['db_exists']) {
            $tasks[] = [
                'name' => 'Crear base de datos',
                'command' => 'docker-compose exec web wp db create',
                'priority' => 3,
                'severity' => 'high',
            ];
        }
        
        // Tarea 4: Instalar WordPress
        if ($state['containers_running'] && !$state['wp_installed']) {
            $tasks[] = [
                'name' => 'Instalar WordPress',
                'command' => 'bedrock setup',
                'priority' => 4,
                'severity' => 'high',
            ];
        }
        
        // Tarea 5: Configurar Acorn
        if ($state['acorn_installed'] && !$state['acorn_configured']) {
            $tasks[] = [
                'name' => 'Configurar Acorn',
                'command' => 'bedrock acorn',
                'priority' => 5,
                'severity' => 'medium',
            ];
        }
        
        return $tasks;
    }
    
    public function readConfiguration(): array
    {
        return $this->validationService->getProjectConfiguration(getcwd());
    }
    
    private function readEnvFile(): array
    {
        return $this->validationService->readEnvFile(getcwd());
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
        return $this->validationService->validateDatabase(getcwd())->isValid;
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
        $prefix = $this->getTablePrefix();
        
        $process = new Process([
            'docker-compose', 'exec', '-T', 'mysql',
            'mysql', "-u{$dbUser}", "-p{$dbPass}", $dbName,
            '-e', "SHOW TABLES LIKE '{$prefix}%';"
        ]);
        $process->run();
        
        $output = $process->getOutput();
        return $process->isSuccessful() && !empty(trim($output)) && str_contains($output, $prefix);
    }
    
    public function getTablePrefix(): string
    {
        $env = $this->readEnvFile();
        
        // 1. PRIORIDAD ABSOLUTA: Usar DB_PREFIX de .env si existe
        if (isset($env['DB_PREFIX']) && !empty($env['DB_PREFIX'])) {
            return rtrim($env['DB_PREFIX'], '_') . '_';
        }
        
        // 2. Detectar prefijo desde config/application.php
        $wpConfig = getcwd() . '/config/application.php';
        if (file_exists($wpConfig)) {
            $content = file_get_contents($wpConfig);
            if (preg_match("/Config::define\('DB_PREFIX',\s*'([^']+)'/", $content, $matches)) {
                return $matches[1];
            }
        }
        
        // 3. Intentar detectar desde base de datos (solo si no está en .env)
        if ($this->areContainersRunning() && $this->databaseExists()) {
            $detected = $this->detectPrefixFromDatabase();
            if ($detected) {
                return $detected;
            }
        }
        
        // 4. Usar prefijo por defecto de WordPress
        return 'wp_';
    }
    
    private function detectPrefixFromDatabase(): ?string
    {
        $env = $this->readEnvFile();
        $dbName = $env['DB_NAME'] ?? 'bedrock';
        $dbUser = $env['DB_USER'] ?? 'root';
        $dbPass = $env['DB_PASSWORD'] ?? 'mysql';
        
        // Obtener todas las tablas
        $process = new Process([
            'docker-compose', 'exec', '-T', 'mysql',
            'mysql', "-u{$dbUser}", "-p{$dbPass}", $dbName,
            '-e', 'SHOW TABLES;'
        ]);
        $process->run();
        
        if (!$process->isSuccessful()) {
            return null;
        }
        
        $output = $process->getOutput();
        $lines = explode("\n", trim($output));
        
        // Extraer prefijos únicos
        $prefixes = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_contains($line, 'Tables_in_')) {
                continue;
            }
            
            // Extraer prefijo (todo antes del primer _)
            if (preg_match('/^([a-z0-9]+)_/', $line, $matches)) {
                $prefix = $matches[1] . '_';
                $prefixes[$prefix] = true;
            }
        }
        
        // Validar cada prefijo consultando la tabla options
        foreach (array_keys($prefixes) as $prefix) {
            $testProcess = new Process([
                'docker-compose', 'exec', '-T', 'mysql',
                'mysql', "-u{$dbUser}", "-p{$dbPass}", $dbName,
                '-e', "SELECT option_value FROM {$prefix}options WHERE option_name='siteurl' LIMIT 1;"
            ]);
            $testProcess->run();
            
            // Si la consulta es exitosa y retorna una URL, este es el prefijo correcto
            if ($testProcess->isSuccessful()) {
                $result = trim($testProcess->getOutput());
                if (!empty($result) && !str_contains($result, 'ERROR') && str_contains($result, 'http')) {
                    return $prefix;
                }
            }
        }
        
        return null;
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
        return $this->validationService->validateDocker(getcwd())->isValid;
    }
    
    private function isWordPressInstalled(): bool
    {
        return $this->validationService->validateWordPress(getcwd())->isValid;
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
        $process = new Process(['docker-compose', 'ps', '-q']);
        $process->run();
        
        if (!$process->isSuccessful()) {
            return false;
        }
        
        $output = trim($process->getOutput());
        if (empty($output)) {
            return false;
        }
        
        // Verificar que al menos un contenedor esté corriendo
        $lines = explode("\n", $output);
        foreach ($lines as $containerId) {
            $containerId = trim($containerId);
            if (empty($containerId)) continue;
            
            $inspectProcess = new Process(['docker', 'inspect', '-f', '{{.State.Running}}', $containerId]);
            $inspectProcess->run();
            
            if ($inspectProcess->isSuccessful() && trim($inspectProcess->getOutput()) === 'true') {
                return true;
            }
        }
        
        return false;
    }
}
