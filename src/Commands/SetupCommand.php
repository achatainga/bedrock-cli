<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SetupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('setup')
            ->setDescription('Automatizar setup completo de WordPress y Acorn')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL del sitio', 'http://localhost')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Título del sitio', 'Mi Sitio')
            ->addOption('admin-user', null, InputOption::VALUE_REQUIRED, 'Usuario admin', 'admin')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Contraseña admin', 'admin')
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email admin', 'admin@example.com')
            ->addOption('skip-wp-install', null, InputOption::VALUE_NONE, 'Saltar instalación de WordPress (si ya importaste SQL)')
            ->addOption('skip-acorn', null, InputOption::VALUE_NONE, 'Saltar configuración de Acorn');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>🚀 Iniciando setup automático...</info>');
        $output->writeln('');

        // 1. Verificar que estamos en un proyecto Bedrock
        if (!file_exists('composer.json')) {
            $output->writeln('<error>Error: No se encontró composer.json. Ejecuta este comando desde la raíz del proyecto.</error>');
            return Command::FAILURE;
        }

        // 2. Verificar que Docker está corriendo
        $output->writeln('<comment>Verificando Docker...</comment>');
        $process = new Process(['docker-compose', 'ps']);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln('<error>Error: Docker no está corriendo. Ejecuta: docker-compose up -d</error>');
            return Command::FAILURE;
        }
        $output->writeln('<info>✓ Docker corriendo</info>');
        $output->writeln('');

        // 3. Instalar WordPress (opcional)
        if (!$input->getOption('skip-wp-install')) {
            $output->writeln('<comment>Instalando WordPress...</comment>');
            
            $url = $input->getOption('url');
            $title = $input->getOption('title');
            $adminUser = $input->getOption('admin-user');
            $adminPassword = $input->getOption('admin-password');
            $adminEmail = $input->getOption('admin-email');
            
            $process = new Process([
                'docker-compose', 'exec', 'web', 'wp', 'core', 'install',
                '--url=' . $url,
                '--title=' . $title,
                '--admin_user=' . $adminUser,
                '--admin_password=' . $adminPassword,
                '--admin_email=' . $adminEmail
            ]);
            $process->setTimeout(120);
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $output->writeln('<error>Error al instalar WordPress</error>');
                return Command::FAILURE;
            }
            $output->writeln('<info>✓ WordPress instalado</info>');
            $output->writeln('');
        } else {
            $output->writeln('<comment>⏭️  Saltando instalación de WordPress</comment>');
            $output->writeln('');
        }

        // 4. Configurar Acorn (opcional)
        if (!$input->getOption('skip-acorn') && file_exists('vendor/roots/acorn')) {
            $output->writeln('<comment>Configurando Acorn...</comment>');
            
            // Inicializar storage
            $process = new Process(['docker-compose', 'exec', 'web', 'wp', 'acorn', 'acorn:init', 'storage']);
            $process->setTimeout(60);
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $output->writeln('<error>Error al inicializar Acorn storage</error>');
                return Command::FAILURE;
            }

            // Publicar configs
            $process = new Process(['docker-compose', 'exec', 'web', 'wp', 'acorn', 'vendor:publish', '--tag=acorn']);
            $process->setTimeout(60);
            $process->run(function ($type, $buffer) use ($output) {
                $output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $output->writeln('<error>Error al publicar configs de Acorn</error>');
                return Command::FAILURE;
            }

            $output->writeln('<info>✓ Acorn configurado</info>');
            $output->writeln('');
        } else {
            $output->writeln('<comment>⏭️  Saltando configuración de Acorn</comment>');
            $output->writeln('');
        }

        $output->writeln('');
        $output->writeln('<info>✅ Setup completado exitosamente</info>');
        $output->writeln('');
        $output->writeln('<comment>Próximos pasos:</comment>');
        $output->writeln('  - Visita: ' . $input->getOption('url'));
        $output->writeln('  - Usuario: ' . $input->getOption('admin-user'));
        $output->writeln('  - Contraseña: ' . $input->getOption('admin-password'));
        $output->writeln('');
        $output->writeln('<comment>Comandos útiles:</comment>');
        $output->writeln('  php vendor/bin/bedrock db:clean --old-prefix=hp2f_ --new-prefix=wp_  # Limpiar BD importada');
        $output->writeln('  docker-compose exec web wp plugin list                                # Listar plugins');
        $output->writeln('  docker-compose exec web wp acorn --help                               # Comandos Acorn');

        return Command::SUCCESS;
    }
}
