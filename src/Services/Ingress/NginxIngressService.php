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

    private function canRunSudo(): bool
    {
        $proc = new Process(['sudo', '-n', 'true']);
        $proc->run();
        return $proc->isSuccessful();
    }
}
