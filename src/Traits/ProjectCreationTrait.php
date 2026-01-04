<?php

namespace Roots\BedrockCli\Traits;

use Symfony\Component\Console\Output\OutputInterface;
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
