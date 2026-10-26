<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services;

use Symfony\Component\Console\Output\OutputInterface;

class EnvironmentSecurityService
{
    /**
     * Detects if the current host environment is a public / staging / production server
     * or a shared / cPanel host requiring strict 127.0.0.1 reverse-proxy binding.
     */
    public static function isPublicOrServerHost(): bool
    {
        // 1. Explicit environment variable override
        $env = getenv('BEDROCK_ENV') ?: getenv('APP_ENV') ?: '';
        if (in_array(strtolower($env), ['production', 'staging', 'live', 'public', 'server'], true)) {
            return true;
        }

        // 2. cPanel / WHM / Ingress host detection
        if (file_exists('/usr/local/cpanel/version') || is_dir('/etc/nginx/conf.d/users') || is_dir('/var/cpanel')) {
            return true;
        }

        // 3. Linux server detection (non-WSL, non-macOS, non-local dev container)
        if (PHP_OS_FAMILY === 'Linux') {
            if (!file_exists('/run/WSL') && !file_exists('/proc/version_signature')) {
                $hostname = gethostname() ?: '';
                if (
                    str_contains($hostname, 'godaddy') ||
                    str_contains($hostname, 'host') ||
                    str_contains($hostname, 'vps') ||
                    str_contains($hostname, 'server') ||
                    str_contains($hostname, 'cpanel')
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Generates a cryptographically strong random alphanumeric password / token.
     */
    public static function generateSecureSecret(int $length = 32): string
    {
        $bytesNeeded = (int) ceil($length / 2);
        return substr(bin2hex(random_bytes($bytesNeeded)), 0, $length);
    }

    /**
     * Computes the safe host port binding for a container service.
     * On public/cPanel/reverse-proxy servers, or for sensitive database services (MySQL, Redis),
     * always binds explicitly to 127.0.0.1 to prevent Docker raw NAT internet exposure.
     *
     * @param int|string $port
     * @param bool $forceLocalhost If true, forces binding to 127.0.0.1 regardless of environment (e.g. for MySQL)
     */
    public static function getSafePortBinding(int|string $port, bool $forceLocalhost = false): string
    {
        $portStr = (string) $port;
        if (empty($portStr)) {
            return '';
        }

        // Already has an IP prefix (e.g. "127.0.0.1:8080")
        if (str_contains($portStr, ':')) {
            return $portStr;
        }

        if ($forceLocalhost || self::isPublicOrServerHost()) {
            return "127.0.0.1:{$portStr}";
        }

        return $portStr;
    }

    /**
     * Audits a Bedrock project directory for security misconfigurations.
     *
     * @return array<int, array{type: string, severity: string, message: string}>
     */
    public static function auditProjectSecurity(string $projectPath): array
    {
        $issues = [];
        $composeFile = "{$projectPath}/docker-compose.yml";
        $envFile = "{$projectPath}/.env";

        if (file_exists($composeFile)) {
            $composeContent = file_get_contents($composeFile) ?: '';

            // Check MySQL 0.0.0.0 binding
            if (preg_match('/^\s*-\s*["\']?(\d+):3306["\']?/m', $composeContent, $m)) {
                $issues[] = [
                    'type' => 'mysql_public_exposure',
                    'severity' => 'P0',
                    'message' => "MySQL port is mapped to 0.0.0.0:{$m[1]}. Docker NAT exposes this directly to the internet! Must bind to 127.0.0.1:{$m[1]}:3306.",
                ];
            }

            // Check Redis 0.0.0.0 binding
            if (preg_match('/^\s*-\s*["\']?(\d+):6379["\']?/m', $composeContent, $m)) {
                $issues[] = [
                    'type' => 'redis_public_exposure',
                    'severity' => 'P0',
                    'message' => "Redis port is mapped to 0.0.0.0:{$m[1]}. Docker NAT exposes Redis directly to the internet! Must remove or bind to 127.0.0.1.",
                ];
            }

            // Check Redis requirepass in compose command
            if (str_contains($composeContent, 'redis:7-alpine') || str_contains($composeContent, 'image: redis')) {
                if (!str_contains($composeContent, '--requirepass')) {
                    $issues[] = [
                        'type' => 'redis_missing_requirepass',
                        'severity' => 'P0',
                        'message' => 'Redis service is running without --requirepass. Redis can be hijacked or weaponized via SLAVEOF replication.',
                    ];
                }
            }

            // Check Nginx binding on public/server host
            if (self::isPublicOrServerHost()) {
                if (preg_match('/^\s*-\s*["\']?(\d+):80["\']?/m', $composeContent, $m)) {
                    $issues[] = [
                        'type' => 'nginx_public_exposure',
                        'severity' => 'P1',
                        'message' => "Nginx container port is mapped to 0.0.0.0:{$m[1]} on a public server. Ingress should route exclusively via 127.0.0.1:{$m[1]}.",
                    ];
                }
            }
        }

        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile) ?: '';

            // Check REDIS_PASSWORD
            if (preg_match('/^REDIS_PASSWORD=\s*$/m', $envContent) || preg_match('/^REDIS_PASSWORD=["\']{2}\s*$/m', $envContent)) {
                $issues[] = [
                    'type' => 'redis_empty_password',
                    'severity' => 'P0',
                    'message' => 'REDIS_PASSWORD in .env is empty. Object Cache is unauthenticated.',
                ];
            }

            // Check weak default DB_PASSWORD
            if (preg_match('/^DB_PASSWORD=["\']?(mysql|root|password|123456)["\']?\s*$/m', $envContent, $m)) {
                $issues[] = [
                    'type' => 'mysql_weak_password',
                    'severity' => 'P0',
                    'message' => "DB_PASSWORD is set to insecure default '{$m[1]}'. Must be a cryptographically random secret.",
                ];
            }
        }

        return $issues;
    }

    /**
     * Applies automatic hardening to docker-compose.yml and .env in the given project.
     */
    public static function hardenProjectSecurity(string $projectPath, OutputInterface $output): bool
    {
        $composeFile = "{$projectPath}/docker-compose.yml";
        $envFile = "{$projectPath}/.env";
        $modified = false;

        // 1. Harden .env credentials first
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile) ?: '';

            // Ensure REDIS_PASSWORD is strong
            if (preg_match('/^REDIS_PASSWORD=\s*$/m', $envContent) || preg_match('/^REDIS_PASSWORD=["\']{2}\s*$/m', $envContent) || !preg_match('/^REDIS_PASSWORD=/m', $envContent)) {
                $newRedisPass = self::generateSecureSecret(32);
                if (preg_match('/^REDIS_PASSWORD=.*$/m', $envContent)) {
                    $envContent = preg_replace('/^REDIS_PASSWORD=.*$/m', "REDIS_PASSWORD='{$newRedisPass}'", $envContent);
                } else {
                    $envContent .= "\nREDIS_PASSWORD='{$newRedisPass}'\n";
                }
                $output->writeln('<info>  ✓ Generada contraseña criptográfica segura para Redis (REDIS_PASSWORD)</info>');
                $modified = true;
            }

            // Harden DB_PASSWORD if it's default
            if (preg_match('/^DB_PASSWORD=["\']?(mysql|root)["\']?\s*$/m', $envContent, $m)) {
                $oldPass = $m[1];
                $projectName = basename($projectPath);
                $mysqlContainer = "{$projectName}_mysql";
                $newDbPass = self::generateSecureSecret(24);

                // If MySQL container is running, rotate password atomically inside the database
                $proc = new \Symfony\Component\Process\Process([
                    'docker', 'exec', $mysqlContainer,
                    'mysql', '-u', 'root', "-p{$oldPass}", '-e',
                    "ALTER USER 'root'@'%' IDENTIFIED BY '{$newDbPass}'; FLUSH PRIVILEGES;"
                ]);
                $proc->run();

                if ($proc->isSuccessful()) {
                    $envContent = preg_replace('/^DB_PASSWORD=["\']?(mysql|root)["\']?\s*$/m', "DB_PASSWORD='{$newDbPass}'", $envContent);
                    $output->writeln('<info>  ✓ Actualizada contraseña de MySQL atómicamente en la BD activa y sincronizada en .env</info>');
                    $modified = true;
                } else {
                    $output->writeln('<comment>  ℹ Contraseña de MySQL en .env es por defecto. Para rotarla en caliente sin corte, ejecuta el cambio en la base de datos antes de modificar .env.</comment>');
                }
            }

            if ($modified) {
                file_put_contents($envFile, $envContent);
            }
        }

        // 2. Harden docker-compose.yml
        if (file_exists($composeFile)) {
            $composeContent = file_get_contents($composeFile) ?: '';

            // Enforce 127.0.0.1 on MySQL
            if (preg_match('/^(\s*-\s*["\']?)(\d+)(:3306["\']?)/m', $composeContent)) {
                $composeContent = preg_replace(
                    '/^(\s*-\s*["\']?)(\d+)(:3306["\']?)/m',
                    '${1}127.0.0.1:${2}${3}',
                    $composeContent
                );
                $output->writeln('<info>  ✓ MySQL vinculado exclusivamente a 127.0.0.1 en docker-compose.yml</info>');
                $modified = true;
            }

            // Enforce 127.0.0.1 on Nginx if on server host
            if (self::isPublicOrServerHost() && preg_match('/^(\s*-\s*["\']?)(\d+)(:80["\']?)/m', $composeContent)) {
                $composeContent = preg_replace(
                    '/^(\s*-\s*["\']?)(\d+)(:80["\']?)/m',
                    '${1}127.0.0.1:${2}${3}',
                    $composeContent
                );
                $output->writeln('<info>  ✓ Nginx vinculado exclusivamente a 127.0.0.1 en docker-compose.yml</info>');
                $modified = true;
            }

            // Enforce 127.0.0.1 on Redis if ports exist
            if (preg_match('/^(\s*-\s*["\']?)(\d+)(:6379["\']?)/m', $composeContent)) {
                $composeContent = preg_replace(
                    '/^(\s*-\s*["\']?)(\d+)(:6379["\']?)/m',
                    '${1}127.0.0.1:${2}${3}',
                    $composeContent
                );
                $output->writeln('<info>  ✓ Redis vinculado exclusivamente a 127.0.0.1 en docker-compose.yml</info>');
                $modified = true;
            }

            // Enforce --requirepass and --save "" on redis-server
            if (str_contains($composeContent, 'redis-server') && !str_contains($composeContent, '--requirepass')) {
                $composeContent = preg_replace(
                    '/command:\s*redis-server(.*)$/m',
                    'command: redis-server --requirepass "${REDIS_PASSWORD}" --save ""$1',
                    $composeContent
                );
                $output->writeln('<info>  ✓ Añadido --requirepass y desactivado snapshot en disco (--save "") para Redis</info>');
                $modified = true;
            }

            if ($modified) {
                file_put_contents($composeFile, $composeContent);
            }
        }

        return $modified;
    }
}
