<?php

declare(strict_types=1);

namespace Roots\BedrockCli\Services\Ingress;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class NginxIngressService
{
    /**
     * Comprueba si el entorno es un servidor cPanel con Nginx Reverse Proxy.
     */
    public function isCpanelNginxHost(): bool
    {
        return is_dir('/etc/nginx/conf.d/users') || file_exists('/usr/local/cpanel/version');
    }

    /**
     * Detecta el usuario de cPanel actual.
     */
    public function detectCpanelUser(): string
    {
        $user = getenv('USER') ?: get_current_user();
        if ($user && $user !== 'root') {
            return $user;
        }

        // Intento de inferir desde HOME
        $home = getenv('HOME') ?: '';
        if (preg_match('#^/home/([^/]+)#', $home, $matches)) {
            return $matches[1];
        }

        return $user ?: 'qqgi77wff00i';
    }

    /**
     * Configura el bloque de proxy reverso en Nginx para el subdominio indicado.
     */
    public function configureSubdomainProxy(string $domain, int $targetPort, OutputInterface $output): bool
    {
        if (empty($domain) || !preg_match('/^[a-zA-Z0-9\.\-]+$/', $domain) || str_contains($domain, '..')) {
            throw new \InvalidArgumentException("Nombre de dominio inválido para Ingress: '{$domain}'");
        }
        if ($targetPort < 1 || $targetPort > 65535) {
            throw new \InvalidArgumentException("Puerto inválido para Ingress: {$targetPort}");
        }

        $user = $this->detectCpanelUser();
        if (empty($user) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $user)) {
            throw new \InvalidArgumentException("Usuario cPanel inválido: '{$user}'");
        }

        $targetDir = "/etc/nginx/conf.d/users/{$user}/{$domain}";
        $confFile = "{$targetDir}/bedrock-proxy.conf";

        $output->writeln("<info>Configurando Ingress Nginx para {$domain} -> http://127.0.0.1:{$targetPort}...</info>");

        $proxyConfig = <<<CONF
# Bedrock Subdomain Ingress Override
# Overrides upstream Apache backend to the local Docker Bedrock container
set \$CPANEL_APACHE_PROXY_PASS http://127.0.0.1:{$targetPort};
CONF;

        $hasSudo = $this->canRunSudo();

        // Crear directorio si no existe
        $mkdirCmd = $hasSudo
            ? ['sudo', 'mkdir', '-p', $targetDir]
            : ['mkdir', '-p', $targetDir];
        
        $mkdirProc = new Process($mkdirCmd);
        $mkdirProc->run();

        // Escribir archivo de configuración usando tee para manejar permisos
        $writeCmd = $hasSudo
            ? ['sudo', 'tee', $confFile]
            : ['tee', $confFile];

        $writeProc = new Process($writeCmd);
        $writeProc->setInput($proxyConfig);
        $writeProc->run();

        if (!$writeProc->isSuccessful()) {
            $output->writeln("<error>✗ No se pudo escribir la configuración en {$confFile}: " . trim($writeProc->getErrorOutput()) . "</error>");
            return false;
        }

        // Validar sintaxis de Nginx
        $testCmd = $hasSudo ? ['sudo', 'nginx', '-t'] : ['nginx', '-t'];
        $testProc = new Process($testCmd);
        $testProc->run();

        if (!$testProc->isSuccessful()) {
            $output->writeln("<error>✗ Error en la sintaxis de Nginx: " . trim($testProc->getErrorOutput()) . "</error>");
            // Revertir archivo para no romper el servicio
            $rmCmd = $hasSudo ? ['sudo', 'rm', '-f', $confFile] : ['rm', '-f', $confFile];
            $rmProc = new Process($rmCmd);
            $rmProc->run();
            return false;
        }

        // Recargar Nginx
        $reloadCmd = $hasSudo ? ['sudo', 'systemctl', 'reload', 'nginx'] : ['nginx', '-s', 'reload'];
        $reloadProc = new Process($reloadCmd);
        $reloadProc->run();

        if (!$reloadProc->isSuccessful()) {
            // Intentar nginx -s reload como fallback
            $fallbackCmd = $hasSudo ? ['sudo', 'nginx', '-s', 'reload'] : ['nginx', '-s', 'reload'];
            $fallbackProc = new Process($fallbackCmd);
            $fallbackProc->run();
            if (!$fallbackProc->isSuccessful()) {
                $output->writeln("<error>✗ Advertencia: No se pudo recargar Nginx automáticamente.</error>");
                return false;
            }
        }

        $output->writeln("<info>✓ Ingress Nginx activo para {$domain} -> puerto {$targetPort} (SSL habilitado)</info>");
        return true;
    }

    /**
     * Busca certificados SSL existentes en el host (cPanel, Let's Encrypt o sistema).
     */
    public function findHostSslCertificate(string $domain): ?string
    {
        $clean = preg_replace('/:\d+$/', '', preg_replace('#^https?://#', '', $domain));
        if (empty($clean) || !preg_match('/^[a-zA-Z0-9\.\-]+$/', $clean)) {
            return null;
        }

        $candidates = [
            "/var/cpanel/ssl/apache_tls/{$clean}/combined",
            "/var/cpanel/ssl/installed/certs/{$clean}.crt",
            "/etc/letsencrypt/live/{$clean}/fullchain.pem",
            "/etc/ssl/certs/{$clean}.pem",
            "/etc/ssl/certs/{$clean}.crt",
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Genera un certificado auto-firmado de respaldo para pruebas locales/aisladas.
     */
    public function generateSelfSignedCert(string $domain, string $targetCombinedPath): bool
    {
        $clean = preg_replace('/:\d+$/', '', preg_replace('#^https?://#', '', $domain));
        $targetDir = dirname($targetCombinedPath);
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }

        $keyFile = tempnam(sys_get_temp_dir(), 'bcli_key_');
        $crtFile = tempnam(sys_get_temp_dir(), 'bcli_crt_');

        $cmd = [
            'openssl', 'req', '-x509', '-nodes', '-days', '365',
            '-newkey', 'rsa:2048',
            '-keyout', $keyFile,
            '-out', $crtFile,
            '-subj', "/CN={$clean}",
        ];

        $proc = new Process($cmd);
        $proc->run();

        if ($proc->isSuccessful() && file_exists($keyFile) && file_exists($crtFile)) {
            $keyContent = file_get_contents($keyFile) ?: '';
            $crtContent = file_get_contents($crtFile) ?: '';
            file_put_contents($targetCombinedPath, $crtContent . "\n" . $keyContent);
            @unlink($keyFile);
            @unlink($crtFile);
            return true;
        }

        if (file_exists($keyFile)) {
            @unlink($keyFile);
        }
        if (file_exists($crtFile)) {
            @unlink($crtFile);
        }

        return false;
    }

    /**
     * Configura terminación SSL nativa dentro del contenedor Nginx de Bedrock.
     */
    public function enableContainerSsl(
        string $projectDir,
        string $domain,
        ?string $certPath = null,
        ?string $keyPath = null
    ): bool {
        $clean = preg_replace('/:\d+$/', '', preg_replace('#^https?://#', '', $domain));
        $certsDir = rtrim($projectDir, '/\\') . '/docker/nginx/certs';
        if (!is_dir($certsDir)) {
            @mkdir($certsDir, 0755, true);
        }
        $targetCombined = "{$certsDir}/ssl.combined";

        // 1. Obtener o generar certificados
        if ($certPath && file_exists($certPath)) {
            $certContent = file_get_contents($certPath) ?: '';
            if ($keyPath && file_exists($keyPath)) {
                $keyContent = file_get_contents($keyPath) ?: '';
                file_put_contents($targetCombined, $certContent . "\n" . $keyContent);
            } else {
                file_put_contents($targetCombined, $certContent);
            }
        } else {
            $hostCert = $this->findHostSslCertificate($clean);
            if ($hostCert && is_readable($hostCert)) {
                $content = file_get_contents($hostCert) ?: '';
                file_put_contents($targetCombined, $content);
            } else {
                $this->generateSelfSignedCert($clean, $targetCombined);
            }
        }

        if (!file_exists($targetCombined) || filesize($targetCombined) < 10) {
            return false;
        }

        // 2. Modificar docker-compose.yml: cambiar puerto HTTP a SSL 443 y montar certificados
        $composePath = rtrim($projectDir, '/\\') . '/docker-compose.yml';
        if (file_exists($composePath)) {
            $composeContent = file_get_contents($composePath) ?: '';
            // Reemplazar mapeo de puerto 80 por 443 en el contenedor nginx
            $composeContent = preg_replace('/("-?\s*[\'"]?(?:\d+|\{\{HTTP_PORT\}\})):80([\'"]?)/', '$1:443$2', $composeContent);

            // Asegurar volumen de certificados
            if (!str_contains($composeContent, '/etc/nginx/certs')) {
                if (str_contains($composeContent, '- ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf')) {
                    $composeContent = str_replace(
                        '- ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf',
                        "- ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf\n      - ./docker/nginx/certs:/etc/nginx/certs:ro",
                        $composeContent
                    );
                }
            }
            file_put_contents($composePath, $composeContent);
        }

        // 3. Modificar docker/nginx/default.conf: directivas SSL y error_page 497
        $nginxConfPath = rtrim($projectDir, '/\\') . '/docker/nginx/default.conf';
        if (file_exists($nginxConfPath)) {
            $nginxContent = file_get_contents($nginxConfPath) ?: '';

            if (!str_contains($nginxContent, 'listen 443 ssl')) {
                $sslDirectives = <<<SSL
    listen 443 ssl default_server;
    ssl_certificate /etc/nginx/certs/ssl.combined;
    ssl_certificate_key /etc/nginx/certs/ssl.combined;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Redirigir solicitudes HTTP inadvertidas en puerto SSL (código 497)
    error_page 497 301 =307 https://\$http_host\$request_uri;
SSL;
                $nginxContent = preg_replace('/listen\s+80;/', $sslDirectives, $nginxContent, 1);
            }

            // Asegurar fastcgi_param HTTPS on
            if (!str_contains($nginxContent, 'fastcgi_param HTTPS on')) {
                if (str_contains($nginxContent, 'fastcgi_param HTTP_AUTHORIZATION $http_authorization;')) {
                    $nginxContent = str_replace(
                        'fastcgi_param HTTP_AUTHORIZATION $http_authorization;',
                        "fastcgi_param HTTP_AUTHORIZATION \$http_authorization;\n        fastcgi_param HTTPS on;\n        fastcgi_param HTTP_X_FORWARDED_PROTO https;",
                        $nginxContent
                    );
                } else {
                    $nginxContent = str_replace(
                        'include fastcgi_params;',
                        "include fastcgi_params;\n        fastcgi_param HTTPS on;\n        fastcgi_param HTTP_X_FORWARDED_PROTO https;",
                        $nginxContent
                    );
                }
            }

            file_put_contents($nginxConfPath, $nginxContent);
        }

        return true;
    }

    private function canRunSudo(): bool
    {
        $proc = new Process(['sudo', '-n', 'true']);
        $proc->run();
        return $proc->isSuccessful();
    }
}

