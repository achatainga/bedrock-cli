<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\StateDetectorService;

class SetupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('setup')
            ->setDescription('Setup automático de WordPress y Acorn (interactivo)')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL del sitio')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Título del sitio')
            ->addOption('admin-user', null, InputOption::VALUE_REQUIRED, 'Usuario admin')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Contraseña admin')
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email admin')
            ->addOption('skip-wp-install', null, InputOption::VALUE_NONE, 'Saltar instalación de WordPress')
            ->addOption('skip-acorn', null, InputOption::VALUE_NONE, 'Saltar configuración de Acorn')
            ->addOption('tutorial', null, InputOption::VALUE_NONE, 'Modo tutorial (con explicaciones)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $tutorialMode = $input->getOption('tutorial');
        
        // Header
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Setup Automático - Bedrock CLI   </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        // 1. Detectar estado
        $output->writeln('<info>Detectando estado del proyecto...</info>');
        $output->writeln('');
        
        $stateDetector = new StateDetectorService();
        $state = $stateDetector->detectProjectState();
        
        // Mostrar estado
        $this->displayState($output, $state);
        
        // 2. Mostrar inconsistencias si existen
        if (!empty($state['inconsistencies'])) {
            $this->showInconsistencies($output, $state['inconsistencies']);
        }
        
        // 3. Mostrar tareas pendientes
        if (!empty($state['pending_tasks'])) {
            $this->showPendingTasks($output, $state['pending_tasks']);
        }
        
        // 4. Validar prerequisitos
        if (!$this->validatePrerequisites($output, $state)) {
            return Command::FAILURE;
        }
        
        // 5. Determinar modo (local vs global)
        if ($state['is_local_install']) {
            $output->writeln('<info>➡️  Modo: Proyecto existente (instalación local)</info>');
            $output->writeln('');
        }
        
        // 6. Asegurar Docker corriendo
        if (!$this->ensureDockerRunning($input, $output, $state, $tutorialMode)) {
            return Command::FAILURE;
        }
        
        // 7. Obtener configuración (interactivo o flags)
        $config = $this->getConfiguration($input, $output, $helper, $state);
        
        // 8. Mostrar resumen
        $this->showSummary($output, $config, $state);
        
        // 9. Confirmar
        $question = new ConfirmationQuestion('¿Continuar? (y/n): ', false);
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Setup cancelado</comment>');
            return Command::SUCCESS;
        }
        
        // 10. Ejecutar setup
        return $this->runSetup($input, $output, $config, $state, $tutorialMode);
    }
    
    private function displayState(OutputInterface $output, array $state): void
    {
        $output->writeln('<fg=cyan>Estado Actual:</>');
        $output->writeln($this->formatStatus($state['is_bedrock'], 'Proyecto Bedrock detectado'));
        $output->writeln($this->formatStatus($state['docker_installed'], 'Docker Desktop instalado'));
        $output->writeln($this->formatStatus($state['docker_running'], 'Docker corriendo'));
        $output->writeln($this->formatStatus($state['containers_running'], 'Contenedores activos'));
        $output->writeln($this->formatStatus($state['db_exists'], 'Base de datos existe'));
        
        if ($state['db_exists']) {
            $output->writeln($this->formatStatus($state['db_has_tables'], 'Base de datos tiene tablas'));
        }
        
        $output->writeln($this->formatStatus($state['wp_installed'], 'WordPress instalado'));
        
        if ($state['acorn_installed']) {
            $output->writeln($this->formatStatus($state['acorn_configured'], 'Acorn configurado'));
        }
        
        $output->writeln('');
    }
    
    private function formatStatus(bool $status, string $message): string
    {
        $icon = $status ? '<info>✓</info>' : '<error>✗</error>';
        return " {$icon} {$message}";
    }
    
    private function validatePrerequisites(OutputInterface $output, array $state): bool
    {
        $errors = [];
        
        if (!$state['is_bedrock']) {
            $errors[] = 'No es un proyecto Bedrock (falta composer.json con roots/bedrock)';
        }
        
        if (!$state['env_exists']) {
            $errors[] = 'Falta archivo .env (ejecuta: cp .env.example .env)';
        }
        
        if (!$state['docker_installed']) {
            $errors[] = 'Docker no está instalado (ejecuta: bedrock doctor)';
        }
        
        if (!empty($errors)) {
            $output->writeln('<error>Errores detectados:</error>');
            foreach ($errors as $error) {
                $output->writeln("  • {$error}");
            }
            $output->writeln('');
            return false;
        }
        
        return true;
    }
    
    private function ensureDockerRunning(InputInterface $input, OutputInterface $output, array $state, bool $tutorialMode): bool
    {
        if ($state['docker_running']) {
            return true;
        }
        
        $output->writeln('<comment>⚠️  Docker no está corriendo</comment>');
        
        if ($tutorialMode) {
            $output->writeln('');
            $output->writeln('<fg=cyan>📚 ¿Por qué necesito Docker?</>');
            $output->writeln('Docker proporciona los contenedores (web, nginx, mysql, redis)');
            $output->writeln('Sin Docker, WordPress no puede ejecutarse');
            $output->writeln('');
        }
        
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('¿Deseas que inicie Docker automáticamente? (y/n): ', false);
        
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<error>Setup cancelado. Inicia Docker manualmente: docker-compose up -d</error>');
            return false;
        }
        
        $output->writeln('<info>Iniciando Docker Desktop...</info>');
        
        $docker = new DockerService();
        $docker->up();
        
        sleep(5);
        
        return true;
    }
    
    private function getConfiguration(InputInterface $input, OutputInterface $output, $helper, array $state): array
    {
        // Leer configuración existente del .env
        $env = $state['config']['env'] ?? [];
        $docker = $state['config']['docker_compose'] ?? [];
        
        // Defaults desde archivos existentes
        $defaultUrl = $env['WP_HOME'] ?? 'http://localhost:8080';
        $defaultDbName = $env['DB_NAME'] ?? $docker['db_name'] ?? 'bedrock';
        
        // Si hay flags, usarlos
        if ($input->getOption('url')) {
            return [
                'url' => $input->getOption('url'),
                'title' => $input->getOption('title') ?? 'Mi Sitio',
                'admin_user' => $input->getOption('admin-user') ?? 'admin',
                'admin_password' => $input->getOption('admin-password') ?? 'admin',
                'admin_email' => $input->getOption('admin-email') ?? 'admin@example.com',
            ];
        }
        
        // Mostrar configuración actual si existe
        if (!empty($env)) {
            $output->writeln('<fg=cyan>Configuración actual (desde .env):</>');
            $output->writeln("  URL: <fg=white>{$defaultUrl}</>");
            $output->writeln("  BD: <fg=white>{$defaultDbName}</>");
            $output->writeln('');
        }
        
        $output->writeln('<fg=cyan>Configuración de WordPress:</>');
        $output->writeln('');
        
        // Modo interactivo con defaults desde .env
        $url = $helper->ask($input, $output, new Question("URL del sitio [{$defaultUrl}]: ", $defaultUrl));
        $title = $helper->ask($input, $output, new Question('Título del sitio [Mi Sitio]: ', 'Mi Sitio'));
        $adminUser = $helper->ask($input, $output, new Question('Usuario admin [admin]: ', 'admin'));
        $adminPassword = $helper->ask($input, $output, new Question('Contraseña admin [admin]: ', 'admin'));
        $adminEmail = $helper->ask($input, $output, new Question('Email admin [admin@example.com]: ', 'admin@example.com'));
        
        return compact('url', 'title', 'adminUser', 'adminPassword', 'adminEmail');
    }
    
    private function showSummary(OutputInterface $output, array $config, array $state): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>Resumen de acciones:</>');
        
        $step = 1;
        
        if (!$state['docker_running']) {
            $output->writeln(" {$step}. Iniciar Docker");
            $step++;
        }
        
        if (!$state['wp_installed']) {
            $output->writeln(" {$step}. Instalar WordPress");
            $output->writeln("    - URL: {$config['url']}");
            $output->writeln("    - Título: {$config['title']}");
            $output->writeln("    - Usuario: {$config['adminUser']}");
            $step++;
        }
        
        if ($state['acorn_installed'] && !$state['acorn_configured']) {
            $output->writeln(" {$step}. Configurar Acorn");
            $output->writeln("    - Inicializar storage");
            $output->writeln("    - Publicar configs");
        }
        
        $output->writeln('');
    }
    
    private function runSetup(InputInterface $input, OutputInterface $output, array $config, array $state, bool $tutorialMode): int
    {
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        
        $output->writeln('');
        $output->writeln('<info>Ejecutando setup...</info>');
        $output->writeln('');
        
        // Paso 0: Actualizar archivos de configuración si cambió URL/puerto
        $urlChanged = $this->updateConfigurationFiles($output, $config, $state);
        
        // Paso 1: Instalar WordPress
        if (!$state['wp_installed'] && !$input->getOption('skip-wp-install')) {
            $output->writeln('<comment>Instalando WordPress...</comment>');            
            $process = $wpcli->coreInstall(
                $config['url'],
                $config['title'],
                $config['adminUser'],
                $config['adminPassword'],
                $config['adminEmail']
            );
            
            $this->runWithLoader($process, $output, 'Instalando WordPress');
            
            if (!$process->isSuccessful()) {
                $output->writeln('<error>✗ Error al instalar WordPress</error>');
                return Command::FAILURE;
            }
            
            $output->writeln('<info>✓ WordPress instalado</info>');
        }
        
        // Paso 1.5: Si WordPress ya existe y cambió URL, actualizar en BD
        if ($state['wp_installed'] && $urlChanged) {
            $output->writeln('');
            $output->writeln('<comment>Actualizando URL en WordPress...</comment>');
            
            $process = $wpcli->custom("option update home '{$config['url']}'");
            $this->runWithLoader($process, $output, 'Actualizando home');
            
            $process = $wpcli->custom("option update siteurl '{$config['url']}/wp'");
            $this->runWithLoader($process, $output, 'Actualizando siteurl');
            
            $output->writeln('<info>✓ URL actualizada en WordPress</info>');
        }
        
        // Paso 2: Configurar Acorn
        if ($state['acorn_installed'] && !$state['acorn_configured'] && !$input->getOption('skip-acorn')) {
            $output->writeln('');
            $output->writeln('<comment>Configurando Acorn...</comment>');
            
            // Inicializar storage
            $process = $wpcli->custom('acorn acorn:init storage');
            $this->runWithLoader($process, $output, 'Inicializando storage');
            
            // Publicar configs
            $process = $wpcli->custom('acorn vendor:publish --tag=acorn');
            $this->runWithLoader($process, $output, 'Publicando configs');
            
            $output->writeln('<info>✓ Acorn configurado</info>');
        }
        
        // Resumen final
        $output->writeln('');
        $output->writeln('<fg=green;options=bold>✓ Setup completado exitosamente</>');
        $output->writeln('');
        $output->writeln('<fg=cyan>Próximos pasos:</>');
        $output->writeln("  • Visita: <fg=white>{$config['url']}</>");
        $output->writeln("  • Login: <fg=white>{$config['url']}/wp/wp-admin</>");
        $output->writeln("  • Usuario: <fg=white;options=bold>{$config['adminUser']}</>");
        $output->writeln("  • Contraseña: <fg=white;options=bold>{$config['adminPassword']}</>");
        $output->writeln('');
        
        return Command::SUCCESS;
    }
    
    private function showInconsistencies(OutputInterface $output, array $inconsistencies): void
    {
        $output->writeln('<fg=yellow;options=bold>⚠️  Inconsistencias detectadas:</>');
        $output->writeln('');
        
        foreach ($inconsistencies as $issue) {
            $icon = $issue['severity'] === 'error' ? '<error>✗</error>' : '<comment>⚠</comment>';
            $output->writeln("  {$icon} {$issue['message']}");
            $output->writeln("     Archivos: " . implode(', ', $issue['files']));
        }
        
        $output->writeln('');
        $output->writeln('<comment>Sugerencia: Sincroniza los archivos manualmente antes de continuar</comment>');
        $output->writeln('');
    }
    
    private function showPendingTasks(OutputInterface $output, array $tasks): void
    {
        $output->writeln('<fg=cyan;options=bold>📝 Tareas pendientes detectadas:</>');
        $output->writeln('');
        
        foreach ($tasks as $task) {
            $icon = match($task['severity']) {
                'critical' => '<error>❗</error>',
                'high' => '<comment>⚠</comment>',
                'medium' => '<info>ℹ</info>',
                default => '<info>•</info>',
            };
            
            $output->writeln("  {$icon} {$task['name']}");
            $output->writeln("     Comando: <fg=white>{$task['command']}</>");
        }
        
        $output->writeln('');
    }
    
    private function updateConfigurationFiles(OutputInterface $output, array $config, array $state): bool
    {
        $env = $state['config']['env'] ?? [];
        $docker = $state['config']['docker_compose'] ?? [];
        
        $currentUrl = $env['WP_HOME'] ?? '';
        $newUrl = $config['url'];
        
        // Detectar si cambió URL
        if ($currentUrl === $newUrl) {
            return false; // No cambió
        }
        
        $output->writeln('<comment>Detectado cambio de URL...</comment>');
        $output->writeln("  Anterior: <fg=white>{$currentUrl}</>");
        $output->writeln("  Nueva: <fg=white>{$newUrl}</>");
        $output->writeln('');
        
        // Extraer puerto de URL
        $newPort = $this->extractPort($newUrl);
        $currentPort = $docker['web_port'] ?? 8080;
        
        // Actualizar .env
        $this->updateEnvFile($newUrl);
        $output->writeln('<info>✓ .env actualizado</info>');
        
        // Si cambió puerto, actualizar docker-compose.yml y reconstruir
        if ($newPort !== $currentPort) {
            $output->writeln("<comment>Puerto cambió de {$currentPort} a {$newPort}</comment>");
            $this->updateDockerComposePort($newPort);
            $output->writeln('<info>✓ docker-compose.yml actualizado</info>');
            
            // Reconstruir contenedores
            $output->writeln('<comment>Reconstruyendo contenedores...</comment>');
            $dockerService = new DockerService();
            
            $process = $dockerService->down();
            $this->runWithLoader($process, $output, 'Deteniendo contenedores');
            
            sleep(2);
            
            $process = $dockerService->up();
            $this->runWithLoader($process, $output, 'Iniciando contenedores');
            
            sleep(3);
            $output->writeln('<info>✓ Contenedores reconstruidos</info>');
        }
        
        $output->writeln('');
        return true; // URL cambió
    }
    
    private function extractPort(string $url): int
    {
        if (preg_match('/:([0-9]+)/', $url, $matches)) {
            return (int)$matches[1];
        }
        return 80; // Default
    }
    
    private function updateEnvFile(string $newUrl): void
    {
        $envPath = getcwd() . '/.env';
        if (!file_exists($envPath)) {
            return;
        }
        
        $content = file_get_contents($envPath);
        $content = preg_replace(
            "/WP_HOME=.*/",
            "WP_HOME='{$newUrl}'",
            $content
        );
        file_put_contents($envPath, $content);
    }
    
    private function updateDockerComposePort(int $newPort): void
    {
        $dockerPath = getcwd() . '/docker-compose.yml';
        if (!file_exists($dockerPath)) {
            return;
        }
        
        $content = file_get_contents($dockerPath);
        $content = preg_replace(
            '/ports:\s*-\s*"\d+:80"/',
            "ports:\n      - \"{$newPort}:80\"",
            $content
        );
        file_put_contents($dockerPath, $content);
    }
    
    protected function runWithLoader(Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        
        $process->start();
        
        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }
        
        $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
    }
}
