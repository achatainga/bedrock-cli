<?php

namespace Roots\BedrockCli\Commands\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class UpdateConfigCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('docker:update-config')
             ->setDescription('Actualizar configuración Docker desde stubs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln("\n<fg=magenta>═══════════════════════════════════════════════════════════════</>");
        $output->writeln("<fg=magenta>  BEDROCK CLI - UPDATE DOCKER CONFIG</>");
        $output->writeln("<fg=magenta>═══════════════════════════════════════════════════════════════</>\n");

        $projectPath = getcwd();
        
        if (!file_exists($projectPath . '/docker-compose.yml')) {
            $output->writeln("<fg=red>✗ Error: No es un proyecto Docker</>");
            return Command::FAILURE;
        }

        // Buscar stubs de Docker
        $stubsPath = $this->findStubsPath();
        if (!$stubsPath) {
            $output->writeln("<fg=red>✗ Error: No se encontraron stubs de Docker</>");
            return Command::FAILURE;
        }

        $output->writeln("<fg=cyan>→ Actualizando configuración Docker...</>");

        $updated = false;

        // Actualizar nginx config
        $nginxStub = $stubsPath . '/docker/nginx/default.conf.stub';
        $nginxTarget = $projectPath . '/docker/nginx/default.conf';
        
        if (file_exists($nginxStub) && file_exists($nginxTarget)) {
            $stubContent = file_get_contents($nginxStub);
            $currentContent = file_get_contents($nginxTarget);
            
            if ($stubContent !== $currentContent) {
                copy($nginxStub, $nginxTarget);
                $output->writeln("<fg=green>✓ nginx/default.conf actualizado</>");
                $updated = true;
            } else {
                $output->writeln("<fg=yellow>• nginx/default.conf ya está actualizado</>");
            }
        }

        // Actualizar MySQL config si existe
        $mysqlStub = $stubsPath . '/docker/mysql/my.cnf.stub';
        $mysqlTarget = $projectPath . '/docker/mysql/my.cnf';
        
        if (file_exists($mysqlStub) && file_exists($mysqlTarget)) {
            $stubContent = file_get_contents($mysqlStub);
            $currentContent = file_get_contents($mysqlTarget);
            
            if ($stubContent !== $currentContent) {
                copy($mysqlStub, $mysqlTarget);
                $output->writeln("<fg=green>✓ mysql/my.cnf actualizado</>");
                $updated = true;
            } else {
                $output->writeln("<fg=yellow>• mysql/my.cnf ya está actualizado</>");
            }
        }
        
        // Actualizar Dockerfile si existe
        $dockerfileStub = $stubsPath . '/Dockerfile.web.stub';
        $dockerfileTarget = $projectPath . '/Dockerfile.web';
        
        if (file_exists($dockerfileStub) && file_exists($dockerfileTarget)) {
            $stubContent = file_get_contents($dockerfileStub);
            $currentContent = file_get_contents($dockerfileTarget);
            
            if ($stubContent !== $currentContent) {
                copy($dockerfileStub, $dockerfileTarget);
                $output->writeln("<fg=green>✓ Dockerfile.web actualizado</>");
                $updated = true;
            } else {
                $output->writeln("<fg=yellow>• Dockerfile.web ya está actualizado</>");
            }
        }

        if ($updated) {
            $output->writeln("\n<fg=cyan>→ Reiniciando contenedores afectados...</>");
            
            // Reiniciar nginx
            $process = new Process(['docker-compose', 'restart', 'nginx'], $projectPath);
            $process->run();
            
            if ($process->isSuccessful()) {
                $output->writeln("<fg=green>✓ Contenedor nginx reiniciado</>");
            } else {
                $output->writeln("<fg=red>✗ Error reiniciando nginx</>");
            }
            
            // Si se actualizó Dockerfile, rebuild del contenedor web
            if (file_exists($projectPath . '/Dockerfile.web')) {
                $output->writeln("<fg=cyan>→ Reconstruyendo contenedor web...</>");
                $process = new Process(['docker-compose', 'build', 'web'], $projectPath);
                $process->run();
                
                if ($process->isSuccessful()) {
                    $output->writeln("<fg=green>✓ Contenedor web reconstruido</>");
                    
                    $process = new Process(['docker-compose', 'up', '-d', 'web'], $projectPath);
                    $process->run();
                    
                    if ($process->isSuccessful()) {
                        $output->writeln("<fg=green>✓ Contenedor web reiniciado</>");
                    }
                } else {
                    $output->writeln("<fg=red>✗ Error reconstruyendo contenedor web</>");
                }
            }
            
            $output->writeln("\n<fg=green>✓ Configuración Docker actualizada!</>");
        } else {
            $output->writeln("\n<fg=green>✓ Configuración Docker ya está actualizada</>");
        }

        return Command::SUCCESS;
    }

    private function findStubsPath(): ?string
    {
        // Buscar stubs en la instalación global de bedrock-cli
        $possiblePaths = [
            $_SERVER['HOME'] . '/.composer/vendor/achatainga/bedrock-cli/stubs',
            '/usr/local/share/bedrock-cli/stubs',
            __DIR__ . '/../../../stubs'
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path . '/docker/nginx/default.conf.stub')) {
                return $path;
            }
        }

        return null;
    }
}