<?php

namespace Roots\BedrockCli\Commands\Docker;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateConfigCommand extends Command
{
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

        // Actualizar Dockerfile.web
        $dockerfileStub = $stubsDir . '/Dockerfile.web.stub';
        if (file_exists($dockerfileStub)) {
            copy($dockerfileStub, 'Dockerfile.web');
            $output->writeln('<info>✓ Dockerfile.web actualizado</info>');
            $updated = true;
        }

        // NO actualizar docker-compose.yml (requiere variables)
        $output->writeln('<comment>⚠ docker-compose.yml no actualizado (requiere variables del proyecto)</comment>');

        if (!$updated) {
            $output->writeln('<comment>No se encontraron stubs para actualizar</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<info>✓ Configuración Docker actualizada</info>');
        $output->writeln('<comment>Ejecuta "docker-compose build --no-cache web" para aplicar cambios</comment>');

        return Command::SUCCESS;
    }
    
    private function copyStub(string $stub, string $destination, array $vars): void
    {
        $content = file_get_contents($stub);
        $content = str_replace(array_keys($vars), array_values($vars), $content);
        file_put_contents($destination, $content);
    }
}