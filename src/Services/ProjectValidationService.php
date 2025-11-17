<?php

namespace Roots\BedrockCli\Services;

use Roots\BedrockCli\ValueObjects\DockerValidation;
use Roots\BedrockCli\ValueObjects\DatabaseValidation;
use Roots\BedrockCli\ValueObjects\WordPressValidation;
use Roots\BedrockCli\ValueObjects\AcornValidation;

class ProjectValidationService
{
    public function validateDocker(string $projectPath): DockerValidation
    {
        $dockerComposePath = $projectPath . '/docker-compose.yml';
        
        if (!file_exists($dockerComposePath)) {
            return new DockerValidation(false, 'docker-compose.yml not found');
        }

        // Check if Docker is running
        $dockerRunning = $this->isDockerRunning();
        if (!$dockerRunning) {
            return new DockerValidation(false, 'Docker is not running');
        }

        // Check if containers are running
        $containersRunning = $this->areContainersRunning($projectPath);
        
        return new DockerValidation($containersRunning, $containersRunning ? 'Docker containers running' : 'Docker containers not running');
    }

    public function validateDatabase(string $projectPath): DatabaseValidation
    {
        $env = $this->readEnvFile($projectPath);
        
        if (!isset($env['DB_HOST'], $env['DB_NAME'], $env['DB_USER'], $env['DB_PASSWORD'])) {
            return new DatabaseValidation(false, 'Database configuration incomplete');
        }

        // Test database connection
        try {
            $pdo = new \PDO(
                "mysql:host={$env['DB_HOST']};port=" . ($env['DB_PORT'] ?? 3306) . ";dbname={$env['DB_NAME']}",
                $env['DB_USER'],
                $env['DB_PASSWORD']
            );
            return new DatabaseValidation(true, 'Database connection successful');
        } catch (\PDOException $e) {
            return new DatabaseValidation(false, 'Database connection failed: ' . $e->getMessage());
        }
    }

    public function validateWordPress(string $projectPath): WordPressValidation
    {
        $wpPath = $projectPath . '/web/wp';
        
        if (!is_dir($wpPath)) {
            return new WordPressValidation(false, 'WordPress not installed');
        }

        $wpConfigPath = $projectPath . '/web/wp-config.php';
        if (!file_exists($wpConfigPath)) {
            return new WordPressValidation(false, 'wp-config.php not found');
        }

        return new WordPressValidation(true, 'WordPress installed');
    }

    public function validateAcorn(string $projectPath): AcornValidation
    {
        $acornPath = $projectPath . '/web/app/themes';
        
        if (!is_dir($acornPath)) {
            return new AcornValidation(false, 'Themes directory not found');
        }

        // Check for Acorn theme (look for composer.json with acorn dependency)
        $themes = glob($acornPath . '/*/composer.json');
        foreach ($themes as $composerFile) {
            $composer = json_decode(file_get_contents($composerFile), true);
            if (isset($composer['require']['roots/acorn'])) {
                return new AcornValidation(true, 'Acorn theme found');
            }
        }

        return new AcornValidation(false, 'No Acorn theme found');
    }

    public function detectInconsistencies(string $projectPath): array
    {
        $inconsistencies = [];
        $env = $this->readEnvFile($projectPath);
        $dockerCompose = $this->readDockerCompose($projectPath);

        // HTTP Port inconsistency detection (FIX: Only alert when port != 80)
        if (isset($env['WP_HOME'])) {
            $envUrl = parse_url($env['WP_HOME']);
            $envPort = $envUrl['port'] ?? 80; // Default to 80 if not specified
            
            if (isset($dockerCompose['services']['wordpress']['ports'])) {
                foreach ($dockerCompose['services']['wordpress']['ports'] as $portMapping) {
                    if (is_string($portMapping) && strpos($portMapping, ':80') !== false) {
                        $dockerPort = (int) explode(':', $portMapping)[0];
                        
                        // Only report inconsistency if env port is not 80 or docker port is not 80
                        if ($envPort !== 80 && $dockerPort !== 80 && $envPort !== $dockerPort) {
                            $inconsistencies[] = [
                                'type' => 'http_port',
                                'message' => "HTTP port mismatch: .env has port {$envPort}, docker-compose.yml maps to port {$dockerPort}",
                                'env_value' => $envPort,
                                'docker_value' => $dockerPort
                            ];
                        } elseif ($envPort === 80 && $dockerPort !== 80) {
                            $inconsistencies[] = [
                                'type' => 'http_port',
                                'message' => "HTTP port mismatch: .env expects default port 80, but docker-compose.yml maps to port {$dockerPort}",
                                'env_value' => 80,
                                'docker_value' => $dockerPort
                            ];
                        }
                    }
                }
            }
        }

        // Redis Port inconsistency detection (NEW)
        if (isset($env['REDIS_PORT'])) {
            $envRedisPort = (int) $env['REDIS_PORT'];
            
            if (isset($dockerCompose['services']['redis']['ports'])) {
                foreach ($dockerCompose['services']['redis']['ports'] as $portMapping) {
                    if (is_string($portMapping) && strpos($portMapping, ':6379') !== false) {
                        $dockerRedisPort = (int) explode(':', $portMapping)[0];
                        
                        if ($envRedisPort !== $dockerRedisPort) {
                            $inconsistencies[] = [
                                'type' => 'redis_port',
                                'message' => "Redis port mismatch: .env has REDIS_PORT={$envRedisPort}, docker-compose.yml maps to port {$dockerRedisPort}",
                                'env_value' => $envRedisPort,
                                'docker_value' => $dockerRedisPort
                            ];
                        }
                    }
                }
            }
        }

        return $inconsistencies;
    }

    public function getProjectConfiguration(string $projectPath): array
    {
        return [
            'env' => $this->readEnvFile($projectPath),
            'docker_compose' => $this->readDockerCompose($projectPath),
            'project_path' => $projectPath,
            'has_wordpress' => is_dir($projectPath . '/web/wp'),
            'has_docker_compose' => file_exists($projectPath . '/docker-compose.yml'),
            'has_env' => file_exists($projectPath . '/.env')
        ];
    }

    public function readEnvFile(string $projectPath): array
    {
        $envPath = $projectPath . '/.env';
        
        if (!file_exists($envPath)) {
            return [];
        }

        $env = [];
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
                [$key, $value] = explode('=', $line, 2);
                $env[trim($key)] = trim($value, '"\'');
            }
        }

        return $env;
    }

    public function readDockerCompose(string $projectPath): array
    {
        $dockerComposePath = $projectPath . '/docker-compose.yml';
        
        if (!file_exists($dockerComposePath)) {
            return [];
        }

        return yaml_parse_file($dockerComposePath) ?: [];
    }

    private function isDockerRunning(): bool
    {
        $output = shell_exec('docker info 2>/dev/null');
        return $output !== null && strpos($output, 'Server Version') !== false;
    }

    private function areContainersRunning(string $projectPath): bool
    {
        $projectName = basename($projectPath);
        $output = shell_exec("docker ps --filter name={$projectName} --format '{{.Names}}' 2>/dev/null");
        return !empty(trim($output ?? ''));
    }
}