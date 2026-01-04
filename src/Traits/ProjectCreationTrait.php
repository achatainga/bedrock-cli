<?php

namespace Roots\BedrockCli\Traits;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Roots\BedrockCli\Services\WebServerService;

trait ProjectCreationTrait
{
    private WebServerService $webServerService;
    
    /**
     * Detecta web server y determina puerto HTTP apropiado
     */
    protected function determineHttpPort(?int $requestedPort, OutputInterface $output): array
    {
        $webServer = $this->webServerService->detectWebServer();
        $existingProjects = $this->webServerService->detectExistingProjects();
        
        // Si usuario especificó puerto, usarlo
        if ($requestedPort !== null) {
            return [
                'port' => $requestedPort,
                'webServer' => $webServer,
                'strategy' => $requestedPort === 80 ? 'direct' : 'reverse-proxy'
            ];
        }
        
        // Detectar puerto libre
        $freePort = $this->webServerService->findFreePort(80);
        
        // Si hay web server del sistema, recomendar estrategia
        if ($webServer && (!empty($existingProjects) || $freePort !== 80)) {
            $recommendation = $this->webServerService->recommendStrategy($webServer, $freePort);
            
            // Si recomienda reverse proxy, usar puerto 82
            if ($recommendation['strategy'] === 'reverse-proxy') {
                $output->writeln("<comment>⚠️  {$webServer} detectado con proyectos existentes</comment>");
                $output->writeln("<comment>   Usando puerto 82 con reverse proxy (recomendado)</comment>");
                return [
                    'port' => 82,
                    'webServer' => $webServer,
                    'strategy' => 'reverse-proxy',
                    'recommendation' => $recommendation
                ];
            }
        }
        
        // Usar puerto libre detectado
        if ($freePort !== 80) {
            $output->writeln("<comment>Puerto 80 ocupado, usando {$freePort}</comment>");
        }
        
        return [
            'port' => $freePort,
            'webServer' => $webServer,
            'strategy' => $freePort === 80 ? 'direct' : 'reverse-proxy'
        ];
    }
    
    /**
     * Maneja la ubicación del proyecto (mover a document root si es necesario)
     * 
     * @param string $projectName
     * @param string $strategy 'direct' o 'reverse-proxy'
     * @param OutputInterface $output
     * @return string Ruta final del proyecto
     */
    protected function handleProjectLocation(
        string $projectName,
        string $strategy,
        OutputInterface $output
    ): ?string {
        // Solo si usó reverse proxy
        if ($strategy !== 'reverse-proxy') {
            return null;
        }
        
        $currentDir = getcwd();
        $documentRoot = $this->webServerService->detectDocumentRoot();
        
        // Si no detectó document root o ya estamos ahí
        if (!$documentRoot || $currentDir === $documentRoot) {
            return null;
        }
        
        // Si el proyecto ya está en document root
        if (strpos($currentDir . '/' . $projectName, $documentRoot) === 0) {
            return null;
        }
        
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>═══ UBICACIÓN DEL PROYECTO ═══</>');
        $output->writeln("<comment>📁 Document root detectado: {$documentRoot}</comment>");
        $output->writeln("<comment>📍 Ubicación actual: {$currentDir}</comment>");
        $output->writeln('');
        $output->writeln('<fg=yellow>Para que el web server sirva correctamente el proyecto,</>');
        $output->writeln('<fg=yellow>se recomienda moverlo a: ' . $documentRoot . '</>');
        $output->writeln('');
        
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "<fg=yellow>¿Mover proyecto a {$documentRoot}? (S/n):</> ",
            true
        );
        
        if (!$helper->ask($this->input ?? new \Symfony\Component\Console\Input\ArrayInput([]), $output, $question)) {
            $output->writeln('<comment>Proyecto permanecerá en ubicación actual</comment>');
            $output->writeln('<comment>Recuerda moverlo manualmente después:</comment>');
            $output->writeln("<comment>  sudo mv {$currentDir}/{$projectName} {$documentRoot}/</comment>");
            return null;
        }
        
        $sourcePath = $currentDir . '/' . $projectName;
        $targetPath = $documentRoot . '/' . $projectName;
        
        $output->writeln('');
        $output->writeln('<info>Moviendo proyecto...</info>');
        
        // Mover con sudo
        exec("sudo mv {$sourcePath} {$targetPath} 2>&1", $moveOutput, $returnCode);
        
        if ($returnCode !== 0) {
            $output->writeln('<error>✗ Error al mover proyecto: ' . implode("\n", $moveOutput) . '</error>');
            return null;
        }
        
        $output->writeln('<info>✓ Proyecto movido a ' . $targetPath . '</info>');
        
        // Ajustar permisos
        $output->writeln('<info>Ajustando permisos...</info>');
        
        // Detectar usuario del web server
        $webServerUser = $this->detectWebServerUser();
        
        if ($webServerUser) {
            exec("sudo chown -R {$webServerUser}:{$webServerUser} {$targetPath} 2>&1", $chownOutput, $returnCode);
            
            if ($returnCode === 0) {
                $output->writeln("<info>✓ Permisos ajustados (propietario: {$webServerUser})</info>");
            } else {
                $output->writeln('<comment>⚠ No se pudieron ajustar permisos automáticamente</comment>');
                $output->writeln("<comment>Ejecuta: sudo chown -R {$webServerUser}:{$webServerUser} {$targetPath}</comment>");
            }
        }
        
        return $targetPath;
    }
    
    /**
     * Detecta el usuario del web server
     */
    private function detectWebServerUser(): ?string
    {
        $webServer = $this->webServerService->detectWebServer();
        
        if ($webServer === 'nginx') {
            // Verificar usuarios comunes de nginx
            $users = ['www-data', 'nginx', 'apache'];
            foreach ($users as $user) {
                exec("id {$user} 2>/dev/null", $output, $returnCode);
                if ($returnCode === 0) {
                    return $user;
                }
            }
        }
        
        if ($webServer === 'apache') {
            // Verificar usuarios comunes de apache
            $users = ['www-data', 'apache', 'httpd'];
            foreach ($users as $user) {
                exec("id {$user} 2>/dev/null", $output, $returnCode);
                if ($returnCode === 0) {
                    return $user;
                }
            }
        }
        
        return 'www-data'; // Fallback
    }
    
    /**
     * Genera configuración de web server si es necesario
     */
    protected function generateWebServerConfig(
        string $projectName,
        string $webServer,
        int $dockerPort,
        string $domain,
        OutputInterface $output
    ): void {
        if ($dockerPort === 80) {
            return; // No necesita reverse proxy
        }
        
        $output->writeln('');
        $output->writeln('<comment>Generando configuración de reverse proxy...</comment>');
        
        if ($webServer === 'nginx') {
            $config = $this->webServerService->generateNginxConfig($projectName, $domain, $dockerPort);
            $configFile = "{$projectName}/nginx-reverse-proxy.conf";
        } else {
            $config = $this->webServerService->generateApacheConfig($projectName, $domain, $dockerPort);
            $configFile = "{$projectName}/apache-reverse-proxy.conf";
        }
        
        file_put_contents($configFile, $config);
        $output->writeln("<info>✓ Configuración guardada en: {$configFile}</info>");
        $output->writeln('');
        $output->writeln('<comment>Para activar manualmente:</comment>');
        $output->writeln("  sudo cp {$configFile} /etc/{$webServer}/sites-available/{$projectName}");
        $output->writeln("  sudo ln -s /etc/{$webServer}/sites-available/{$projectName} /etc/{$webServer}/sites-enabled/");
        $output->writeln("  sudo {$webServer} -t && sudo systemctl reload {$webServer}");
    }
}
