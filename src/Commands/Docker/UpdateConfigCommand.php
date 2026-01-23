<?php

namespace Roots\BedrockCli\Commands\Docker;

use Roots\BedrockCli\Traits\DockerComposeTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateConfigCommand extends Command
{
    use DockerComposeTrait;
    protected function configure(): void
    {
        $this
            ->setName('docker:update-config')
            ->setDescription('Actualizar configuración Docker desde stubs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Verificar que estamos en un proyecto bedrock
        if (!file_exists('composer.json') || !file_exists('docker-compose.yml')) {
            $output->writeln('<error>No estás en un proyecto Bedrock con Docker</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Actualizando configuración Docker...</info>');

        // Obtener directorio de stubs
        $stubsDir = dirname(__DIR__, 3) . '/stubs';
        
        if (!is_dir($stubsDir)) {
            $output->writeln('<error>No se encontró el directorio de stubs</error>');
            return Command::FAILURE;
        }

        $updated = false;

        // 1. Actualizar Dockerfile.web
        $dockerfileStub = $stubsDir . '/Dockerfile.web.stub';
        if (file_exists($dockerfileStub)) {
            copy($dockerfileStub, 'Dockerfile.web');
            $output->writeln('<info>✓ Dockerfile.web actualizado</info>');
            $updated = true;
        }

        // 2. Intentar actualizar docker-compose.yml detectando variables
        $dockerComposeFile = 'docker-compose.yml';
        $dockerComposeStub = $stubsDir . '/docker-compose.yml.stub';

        if (file_exists($dockerComposeFile) && file_exists($dockerComposeStub)) {
            $content = file_get_contents($dockerComposeFile);
            $vars = [];

            // Detect PROJECT_NAME
            if (preg_match('/container_name: (.*)_web/', $content, $matches)) {
                $vars['{{PROJECT_NAME}}'] = trim($matches[1]);
            }

            // Detect DB_NAME
            if (preg_match('/MYSQL_DATABASE: (.*)/', $content, $matches)) {
                $vars['{{DB_NAME}}'] = trim($matches[1]);
            }

            // Detect DB_PASSWORD (from environment or MYSQL_PWD)
            if (preg_match('/MYSQL_ROOT_PASSWORD: (.*)/', $content, $matches)) {
                $vars['{{DB_PASSWORD}}'] = trim($matches[1]);
            } elseif (preg_match('/MYSQL_PWD=(.*)/', $content, $matches)) {
                $vars['{{DB_PASSWORD}}'] = trim($matches[1]);
            }

            // Detect Ports
            // Nginx port
            if (preg_match('/"(\d+):80"/', $content, $matches)) {
                $vars['{{HTTP_PORT}}'] = $matches[1];
            }
            // MySQL port
            if (preg_match('/"(\d+):3306"/', $content, $matches)) {
                $vars['{{MYSQL_PORT}}'] = $matches[1];
            }
            // Redis port
            if (preg_match('/"(\d+):6379"/', $content, $matches)) {
                $vars['{{REDIS_PORT}}'] = $matches[1];
            }

            // Si detectamos las variables clave, actualizamos
            if (isset($vars['{{PROJECT_NAME}}'])) {
                // Asegurar que tenemos todas las variables con fallbacks
                $vars['{{DB_NAME}}'] = $vars['{{DB_NAME}}'] ?? 'database';
                $vars['{{DB_PASSWORD}}'] = $vars['{{DB_PASSWORD}}'] ?? 'mysql';
                $vars['{{HTTP_PORT}}'] = $vars['{{HTTP_PORT}}'] ?? '80';
                $vars['{{MYSQL_PORT}}'] = $vars['{{MYSQL_PORT}}'] ?? '3306';
                $vars['{{REDIS_PORT}}'] = $vars['{{REDIS_PORT}}'] ?? '6379';

                $this->copyStub($dockerComposeStub, $dockerComposeFile, $vars);
                $output->writeln('<info>✓ docker-compose.yml actualizado (variables auto-detectadas)</info>');
                $updated = true;
            } else {
                $output->writeln('<comment>⚠ docker-compose.yml no actualizado (no se detectaron variables)</comment>');
            }
        }

        if (!$updated) {
            $output->writeln('<comment>No se encontraron stubs para actualizar</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<info>✓ Todo el entorno Docker ha sido actualizado satisfactoriamente</info>');
        $output->writeln('<comment>Siguiente paso:</comment>');
        $output->writeln('<info>' . $this->getDockerComposeCommand() . ' up -d --build</info>');

        return Command::SUCCESS;
    }
    
    private function copyStub(string $stub, string $destination, array $vars): void
    {
        $content = file_get_contents($stub);
        $content = str_replace(array_keys($vars), array_values($vars), $content);
        file_put_contents($destination, $content);
    }
}