<?php

namespace Roots\BedrockCli\Services;

use Roots\BedrockCli\Services\ProjectValidationService;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\OutputInterface;

class DockerVerificationService
{
    private ProjectValidationService $validationService;
    
    public function __construct()
    {
        $this->validationService = new ProjectValidationService();
    }
    public function verifyAndFixProject(string $projectPath, OutputInterface $output): bool
    {
        $output->writeln('<info>Verificando configuración Docker y Filesystem...</info>');
        
        $this->ensureFilesystemConfig($projectPath, $output);
        
        $issues = $this->detectIssues($projectPath);
        
        if (empty($issues)) {
            $output->writeln('<info>✓ Configuración Docker correcta</info>');
            return true;
        }
        
        $output->writeln('<comment>Detectados problemas, aplicando auto-fix...</comment>');
        
        foreach ($issues as $issue) {
            $this->fixIssue($issue, $projectPath, $output);
        }
        
        return $this->verifyFixes($projectPath, $output);
    }
    
    private function detectIssues(string $projectPath): array
    {
        $issues = [];
        
        // Verificar si contenedores están corriendo
        if (!$this->areContainersRunning($projectPath)) {
            $issues[] = 'containers_not_running';
        }
        
        // Verificar directorios Acorn storage
        if (!$this->checkAcornStorage($projectPath)) {
            $issues[] = 'missing_acorn_storage';
        }
        
        // Verificar respuesta del sitio
        if (!$this->checkSiteResponse($projectPath)) {
            $issues[] = 'site_not_responding';
        }
        
        return $issues;
    }
    
    private function areContainersRunning(string $projectPath): bool
    {
        return $this->validationService->validateDocker($projectPath)->isValid;
    }
    
    private function checkAcornStorage(string $projectPath): bool
    {
        return $this->validationService->validateAcorn($projectPath)->isValid;
    }
    
    private function checkSiteResponse(string $projectPath): bool
    {
        $port = $this->getHttpPort($projectPath);
        
        $process = new Process(['curl', '-s', '-o', '/dev/null', '-w', '%{http_code}', "http://localhost:$port"]);
        $process->run();
        
        $httpCode = trim($process->getOutput());
        return in_array($httpCode, ['200', '302', '301']);
    }
    
    private function fixIssue(string $issue, string $projectPath, OutputInterface $output): void
    {
        switch ($issue) {
            case 'containers_not_running':
                $this->startContainers($projectPath, $output);
                break;
                
            case 'missing_acorn_storage':
                $this->createAcornStorage($projectPath, $output);
                break;
                
            case 'site_not_responding':
                $this->fixSiteResponse($projectPath, $output);
                break;
        }
    }
    
    private function startContainers(string $projectPath, OutputInterface $output): void
    {
        $output->writeln('<comment>  → Iniciando contenedores...</comment>');
        
        $process = new Process(['docker-compose', 'up', '-d'], $projectPath);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>  ✓ Contenedores iniciados</info>');
        } else {
            $output->writeln('<error>  ✗ Error al iniciar contenedores</error>');
            $output->writeln($process->getErrorOutput());
        }
    }
    
    private function createAcornStorage(string $projectPath, OutputInterface $output): void
    {
        $output->writeln('<comment>  → Creando directorios Acorn storage y uploads...</comment>');
        
        $containerName = $this->getWebContainerName($projectPath);
        if (!$containerName) return;
        
        $commands = [
            ['docker', 'exec', $containerName, 'mkdir', '-p', '/var/www/html/storage/framework/{cache,views,sessions,testing}'],
            ['docker', 'exec', $containerName, 'mkdir', '-p', '/var/www/html/web/app/uploads'],
            ['docker', 'exec', $containerName, 'chown', '-R', 'www-data:www-data', '/var/www/html/storage', '/var/www/html/web/app/uploads'],
            ['docker', 'exec', $containerName, 'chmod', '-R', '775', '/var/www/html/storage', '/var/www/html/web/app/uploads']
        ];
        
        foreach ($commands as $cmd) {
            $process = new Process($cmd);
            $process->run();
        }
        
        $output->writeln('<info>  ✓ Directorios Acorn y uploads configurados</info>');
    }

    public function ensureFilesystemConfig(string $projectPath, OutputInterface $output): void
    {
        $appConfig = "{$projectPath}/config/application.php";
        if (file_exists($appConfig)) {
            $content = file_get_contents($appConfig);
            if (!str_contains($content, 'FS_METHOD')) {
                $output->writeln('<comment>  → Inyectando FS_METHOD direct en config/application.php...</comment>');
                $fsBlock = "\n/**\n * Filesystem permissions for themes/plugins (e.g. Kirki webfonts, uploads)\n */\nif (!defined('FS_METHOD')) {\n    Config::define('FS_METHOD', env('FS_METHOD') ?: 'direct');\n}\n";
                if (str_contains($content, 'Config::apply();')) {
                    $content = str_replace('Config::apply();', $fsBlock . "Config::apply();", $content);
                } else {
                    $content .= $fsBlock;
                }
                file_put_contents($appConfig, $content);
                $output->writeln('<info>  ✓ FS_METHOD configurado en config/application.php</info>');
            }
        }

        $envFile = "{$projectPath}/.env";
        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            if (!str_contains($envContent, 'FS_METHOD')) {
                $envContent .= "\n# Filesystem\nFS_METHOD='direct'\n";
                file_put_contents($envFile, $envContent);
            }
        }
    }
    
    private function fixSiteResponse(string $projectPath, OutputInterface $output): void
    {
        $output->writeln('<comment>  → Verificando configuración del sitio...</comment>');
        
        // Reiniciar nginx si es necesario
        $containerName = $this->getNginxContainerName($projectPath);
        if ($containerName) {
            $process = new Process(['docker', 'restart', $containerName]);
            $process->run();
        }
        
        $output->writeln('<info>  ✓ Configuración verificada</info>');
    }
    
    private function verifyFixes(string $projectPath, OutputInterface $output): bool
    {
        $output->writeln('<info>Verificando correcciones...</info>');
        
        $remainingIssues = $this->detectIssues($projectPath);
        
        if (empty($remainingIssues)) {
            $output->writeln('<info>✓ Todos los problemas corregidos</info>');
            $this->showSuccessMessage($projectPath, $output);
            return true;
        }
        
        $output->writeln('<comment>⚠ Algunos problemas persisten, ejecuta: bedrock doctor --fix</comment>');
        return false;
    }
    
    private function showSuccessMessage(string $projectPath, OutputInterface $output): void
    {
        $port = $this->getHttpPort($projectPath);
        
        $output->writeln('');
        $output->writeln('<info>🎉 Proyecto listo!</info>');
        $output->writeln("<comment>Sitio disponible en: http://localhost:$port</comment>");
        $output->writeln('<comment>Para instalar WordPress:</comment>');
        $output->writeln("  <fg=cyan>curl http://localhost:$port/wp/wp-admin/install.php</>");
    }
    
    private function getWebContainerName(string $projectPath): ?string
    {
        $process = new Process(['docker-compose', 'ps', '-q', 'web'], $projectPath);
        $process->run();
        
        $containerId = trim($process->getOutput());
        if (!$containerId) return null;
        
        $process = new Process(['docker', 'inspect', '--format={{.Name}}', $containerId]);
        $process->run();
        
        return $process->isSuccessful() ? ltrim(trim($process->getOutput()), '/') : null;
    }
    
    private function getNginxContainerName(string $projectPath): ?string
    {
        $process = new Process(['docker-compose', 'ps', '-q', 'nginx'], $projectPath);
        $process->run();
        
        $containerId = trim($process->getOutput());
        if (!$containerId) return null;
        
        $process = new Process(['docker', 'inspect', '--format={{.Name}}', $containerId]);
        $process->run();
        
        return $process->isSuccessful() ? ltrim(trim($process->getOutput()), '/') : null;
    }
    
    private function getHttpPort(string $projectPath): int
    {
        $config = $this->validationService->getProjectConfiguration($projectPath);
        $env = $config['env'] ?? [];
        
        if (isset($env['WP_HOME'])) {
            $port = parse_url($env['WP_HOME'], PHP_URL_PORT);
            return $port ?? 80;
        }
        
        return 80;
    }
}