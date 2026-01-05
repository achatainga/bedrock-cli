<?php

namespace Roots\BedrockCli\Services;

use Roots\BedrockCli\ValueObjects\DockerValidation;
use Roots\BedrockCli\ValueObjects\DatabaseValidation;
use Roots\BedrockCli\ValueObjects\WordPressValidation;
use Roots\BedrockCli\ValueObjects\AcornValidation;
use Roots\BedrockCli\Services\Management\ContextDetector;
use Roots\BedrockCli\Enums\ExecutionMode;

class ProjectValidationService
{
    private ContextDetector $contextDetector;

    public function __construct(ContextDetector $contextDetector)
    {
        $this->contextDetector = $contextDetector;
    }

    public function validateDocker(string $projectPath): DockerValidation
    {
        $dockerComposePath = $projectPath . '/docker-compose.yml';
        
        if (!file_exists($dockerComposePath)) {
            return new DockerValidation(true, 'No Docker (native mode)');
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

        $dockerComposePath = $projectPath . '/docker-compose.yml';
        $mode = $this->contextDetector->detectExecutionMode();

        // 1. Modo Híbrido: Conexión TCP al host (Docker expuesto)
        if ($mode === ExecutionMode::HYBRID) {
            $dbPort = $env['DB_PORT'] ?? 3306;
            $connection = @fsockopen('127.0.0.1', $dbPort, $errno, $errstr, 2);
            
            if (is_resource($connection)) {
                fclose($connection);
                return new DatabaseValidation(true, "Conexión Híbrida OK (127.0.0.1:{$dbPort})");
            }
            return new DatabaseValidation(false, "Fallo conexión Híbrida a 127.0.0.1:{$dbPort}. Verifica que Docker esté corriendo.");
        }

        // 2. Modo Docker Puro: Usar docker-compose exec
        if ($mode === ExecutionMode::DOCKER) {
            // Check if containers are running first
            if (!$this->areContainersRunning($projectPath)) {
                return new DatabaseValidation(false, 'Docker containers not running');
            }

            // Test database connection via Docker
            $dbName = trim($env['DB_NAME'], '"\'');
            $dbUser = trim($env['DB_USER'], '"\'');
            $dbPass = trim($env['DB_PASSWORD'], '"\'');
            
            exec("docker-compose -f {$projectPath}/docker-compose.yml exec -T mysql mysql -u{$dbUser} -p{$dbPass} -e 'SHOW DATABASES LIKE \"{$dbName}\"' 2>/dev/null", $output, $returnCode);
            
            if ($returnCode === 0 && !empty($output)) {
                return new DatabaseValidation(true, 'Database connection successful (Docker)');
            }
            
            return new DatabaseValidation(false, 'Database connection failed inside Docker');
        }

        // 3. Modo Nativo: Conexión MySQL local
        $dbHost = trim($env['DB_HOST'], '"\'');
        $dbName = trim($env['DB_NAME'], '"\'');
        $dbUser = trim($env['DB_USER'], '"\'');
        $dbPass = trim($env['DB_PASSWORD'], '"\'');

        exec("mysql -h{$dbHost} -u{$dbUser} -p{$dbPass} -e 'SHOW DATABASES LIKE \"{$dbName}\"' 2>/dev/null", $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            foreach ($output as $line) {
                if (strpos($line, $dbName) !== false) {
                    return new DatabaseValidation(true, 'Database exists');
                }
            }
        }

        return new DatabaseValidation(false, 'Database not found');
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

        // Check if WordPress is actually installed in database
        $dbValidation = $this->validateDatabase($projectPath);
        if (!$dbValidation->isValid) {
            return new WordPressValidation(false, 'WordPress files exist but database not accessible');
        }

        // Check if WordPress tables exist
        if (!$this->hasWordPressTables($projectPath)) {
            return new WordPressValidation(false, 'WordPress files exist but not installed in database');
        }

        return new WordPressValidation(true, 'WordPress installed');
    }

    public function validateAcorn(string $projectPath): AcornValidation
    {
        // Check 1: Package installed (from composer.json)
        $packageInstalled = $this->isAcornPackageInstalled($projectPath);
        
        // Check 2: Storage initialized (storage directory exists)
        $storageInitialized = is_dir($projectPath . '/storage');
        
        // Check 3: Configs published (config/app.php exists)
        $configsPublished = file_exists($projectPath . '/config/app.php');
        
        // Overall validity: all three must be true
        $isValid = $packageInstalled && $storageInitialized && $configsPublished;
        
        // Generate descriptive message
        $message = $this->generateAcornMessage($packageInstalled, $storageInitialized, $configsPublished);
        
        return new AcornValidation(
            $packageInstalled,
            $storageInitialized, 
            $configsPublished,
            $isValid,
            $message
        );
    }

    public function validateTheme(string $projectPath, string $themeName): object
    {
        $mode = $this->contextDetector->detectExecutionMode();
        
        if ($mode === ExecutionMode::HYBRID || $mode === ExecutionMode::NATIVE) {
            $cmd = "wp theme status {$themeName} --path={$projectPath}/web 2>/dev/null";
        } else {
            if (!$this->areContainersRunning($projectPath)) {
                return (object)['isValid' => false, 'message' => 'Docker containers not running'];
            }
            $cmd = "cd {$projectPath} && docker-compose exec -T web wp theme status {$themeName} 2>/dev/null";
        }
        
        exec($cmd, $output, $returnCode);
        
        $isActive = false;
        foreach ($output as $line) {
            if (stripos($line, 'Status') !== false && stripos($line, 'Active') !== false) {
                $isActive = true;
                break;
            }
        }
        
        return (object)[
            'isValid' => $isActive,
            'message' => $isActive ? "Theme '{$themeName}' is active" : "Theme '{$themeName}' is not active"
        ];
    }

    public function validatePlugins(string $projectPath): object
    {
        $mode = $this->contextDetector->detectExecutionMode();
        
        if ($mode === ExecutionMode::HYBRID || $mode === ExecutionMode::NATIVE) {
            $cmd = "wp plugin list --status=active --format=count --path={$projectPath}/web 2>/dev/null";
        } else {
            if (!$this->areContainersRunning($projectPath)) {
                return (object)['isValid' => false, 'message' => 'Docker containers not running'];
            }
            $cmd = "cd {$projectPath} && docker-compose exec -T web wp plugin list --status=active --format=count 2>/dev/null";
        }
        
        exec($cmd, $output, $returnCode);
        
        $count = isset($output[0]) ? (int)trim($output[0]) : 0;
        
        return (object)[
            'isValid' => $count > 0,
            'message' => $count > 0 ? "{$count} active plugins" : "No active plugins"
        ];
    }

    public function detectInconsistencies(string $projectPath): array
    {
        $inconsistencies = [];
        $env = $this->readEnvFile($projectPath);
        $dockerCompose = $this->readDockerCompose($projectPath);

        // HTTP Port inconsistency detection
        $dockerHttpPort = $this->getDockerHttpPort($dockerCompose);
        
        if ($dockerHttpPort) {
            $hasWpPort = isset($env['WP_PORT']);
            $envPort = $hasWpPort ? (int) $env['WP_PORT'] : 80;
            
            // Case 1: Docker uses port != 80 but .env lacks WP_PORT
            if ($dockerHttpPort !== 80 && !$hasWpPort) {
                $inconsistencies[] = [
                    'type' => 'wp_port_missing',
                    'message' => "WP_PORT missing: Docker uses port {$dockerHttpPort} but .env lacks WP_PORT. Add: WP_PORT={$dockerHttpPort} and WP_HOME=\"http://localhost:\${WP_PORT}\"",
                    'env_value' => 'missing',
                    'docker_value' => $dockerHttpPort,
                    'suggested_fix' => "WP_PORT={$dockerHttpPort}\nWP_HOME=\"http://localhost:\${WP_PORT}\""
                ];
            }
            // Case 2: Both have ports but they don't match
            elseif ($hasWpPort && $envPort !== $dockerHttpPort) {
                $inconsistencies[] = [
                    'type' => 'http_port_mismatch',
                    'message' => "HTTP port mismatch: .env has WP_PORT={$envPort}, docker-compose.yml maps to port {$dockerHttpPort}",
                    'env_value' => $envPort,
                    'docker_value' => $dockerHttpPort
                ];
            }
            // Case 3: Docker uses port 80 but .env has WP_PORT (causes issues)
            elseif ($dockerHttpPort === 80 && $hasWpPort) {
                $inconsistencies[] = [
                    'type' => 'wp_port_unnecessary',
                    'message' => "WP_PORT unnecessary: Docker uses default port 80, remove WP_PORT from .env and use WP_HOME='http://localhost'",
                    'env_value' => $envPort,
                    'docker_value' => 80,
                    'suggested_fix' => "Remove WP_PORT line and set WP_HOME='http://localhost'"
                ];
            }
        }

        // Redis Port inconsistency detection
        $dockerRedisPort = $this->getDockerRedisPort($dockerCompose);
        if ($dockerRedisPort && isset($env['REDIS_PORT'])) {
            $envRedisPort = (int) $env['REDIS_PORT'];
            
            if ($envRedisPort !== $dockerRedisPort) {
                $inconsistencies[] = [
                    'type' => 'redis_port_mismatch',
                    'message' => "Redis port mismatch: .env has REDIS_PORT={$envRedisPort}, docker-compose.yml maps to port {$dockerRedisPort}",
                    'env_value' => $envRedisPort,
                    'docker_value' => $dockerRedisPort
                ];
            }
        }

        // MySQL Port inconsistency detection
        $dockerMysqlPort = $this->getDockerMysqlPort($dockerCompose);
        if ($dockerMysqlPort && $dockerMysqlPort !== 3306) {
            // Check if .env has DB_PORT or if it should
            $hasDbPort = isset($env['DB_PORT']);
            
            if (!$hasDbPort) {
                $inconsistencies[] = [
                    'type' => 'mysql_port_missing',
                    'message' => "DB_PORT missing: Docker MySQL uses port {$dockerMysqlPort} but .env lacks DB_PORT. Add: DB_PORT={$dockerMysqlPort}",
                    'env_value' => 'missing',
                    'docker_value' => $dockerMysqlPort,
                    'suggested_fix' => "DB_PORT={$dockerMysqlPort}"
                ];
            } elseif ((int) $env['DB_PORT'] !== $dockerMysqlPort) {
                $inconsistencies[] = [
                    'type' => 'mysql_port_mismatch',
                    'message' => "MySQL port mismatch: .env has DB_PORT={$env['DB_PORT']}, docker-compose.yml maps to port {$dockerMysqlPort}",
                    'env_value' => (int) $env['DB_PORT'],
                    'docker_value' => $dockerMysqlPort
                ];
            }
        }

        return $inconsistencies;
    }

    private function getDockerHttpPort(array $dockerCompose): ?int
    {
        // Check web service first (most common)
        if (isset($dockerCompose['services']['web']['ports'])) {
            foreach ($dockerCompose['services']['web']['ports'] as $portMapping) {
                if (is_string($portMapping) && strpos($portMapping, ':80') !== false) {
                    return (int) explode(':', $portMapping)[0];
                }
            }
        }
        
        // Check wordpress service as fallback
        if (isset($dockerCompose['services']['wordpress']['ports'])) {
            foreach ($dockerCompose['services']['wordpress']['ports'] as $portMapping) {
                if (is_string($portMapping) && strpos($portMapping, ':80') !== false) {
                    return (int) explode(':', $portMapping)[0];
                }
            }
        }
        
        return null;
    }

    private function getDockerRedisPort(array $dockerCompose): ?int
    {
        if (isset($dockerCompose['services']['redis']['ports'])) {
            foreach ($dockerCompose['services']['redis']['ports'] as $portMapping) {
                if (is_string($portMapping) && strpos($portMapping, ':6379') !== false) {
                    return (int) explode(':', $portMapping)[0];
                }
            }
        }
        
        return null;
    }

    private function getDockerMysqlPort(array $dockerCompose): ?int
    {
        // Check mysql service
        if (isset($dockerCompose['services']['mysql']['ports'])) {
            foreach ($dockerCompose['services']['mysql']['ports'] as $portMapping) {
                if (is_string($portMapping) && strpos($portMapping, ':3306') !== false) {
                    return (int) explode(':', $portMapping)[0];
                }
            }
        }
        
        // Check db service as fallback
        if (isset($dockerCompose['services']['db']['ports'])) {
            foreach ($dockerCompose['services']['db']['ports'] as $portMapping) {
                if (is_string($portMapping) && strpos($portMapping, ':3306') !== false) {
                    return (int) explode(':', $portMapping)[0];
                }
            }
        }
        
        return null;
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

        // Fallback to manual parsing if yaml extension not available
        if (function_exists('yaml_parse_file')) {
            return yaml_parse_file($dockerComposePath) ?: [];
        }
        
        // Simple regex-based parsing for basic docker-compose structure
        $content = file_get_contents($dockerComposePath);
        $config = ['services' => []];
        
        // Extract service ports
        if (preg_match_all('/^\s*(\w+):\s*$/m', $content, $serviceMatches)) {
            foreach ($serviceMatches[1] as $service) {
                if (preg_match('/^\s*' . $service . ':[\s\S]*?ports:[\s\S]*?"(\d+):(\d+)"/m', $content, $portMatches)) {
                    $config['services'][$service]['ports'] = [$portMatches[1] . ':' . $portMatches[2]];
                }
            }
        }
        
        return $config;
    }

    private function isDockerRunning(): bool
    {
        exec('docker info 2>/dev/null', $output, $returnCode);
        return $returnCode === 0;
    }

    private function areContainersRunning(string $projectPath): bool
    {
        $projectName = basename($projectPath);
        
        exec("docker ps --filter name={$projectName} --format '{{.Names}}' 2>/dev/null", $output, $returnCode);
        
        return $returnCode === 0 && !empty($output);
    }

    private function hasWordPressTables(string $projectPath): bool
    {
        $env = $this->readEnvFile($projectPath);
        
        if (!isset($env['DB_NAME'], $env['DB_USER'], $env['DB_PASSWORD'])) {
            return false;
        }

        $dbName = trim($env['DB_NAME'], '"\'');
        $dbUser = trim($env['DB_USER'], '"\'');
        $dbPass = trim($env['DB_PASSWORD'], '"\'');
        $prefix = trim($env['DB_PREFIX'] ?? 'wp_', '"\'');
        
        $projectName = basename($projectPath);
        $cmd = "docker exec {$projectName}_mysql mysql -u{$dbUser} -p{$dbPass} {$dbName} -e 'SHOW TABLES LIKE \"{$prefix}options\"' 2>/dev/null";
        
        $output = shell_exec($cmd);
        
        return $output && strpos($output, $prefix . 'options') !== false;
    }

    private function isAcornPackageInstalled(string $projectPath): bool
    {
        $composerPath = $projectPath . '/composer.json';
        
        if (!file_exists($composerPath)) {
            return false;
        }
        
        $composer = json_decode(file_get_contents($composerPath), true);
        return isset($composer['require']['roots/acorn']);
    }

    private function generateAcornMessage(bool $packageInstalled, bool $storageInitialized, bool $configsPublished): string
    {
        if ($packageInstalled && $storageInitialized && $configsPublished) {
            return 'Fully configured';
        }
        
        if (!$packageInstalled) {
            return 'Package not installed';
        }
        
        if (!$storageInitialized) {
            return 'Storage not initialized';
        }
        
        if (!$configsPublished) {
            return 'Configs not published';
        }
        
        return 'Partially configured';
    }
}