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
        
        // 2. Validar prerequisitos
        if (!$this->validatePrerequisites($output, $state)) {
            return Command::FAILURE;
        }
        
        // 3. Asegurar Docker corriendo
        if (!$this->ensureDockerRunning($input, $output, $state, $tutorialMode)) {
            return Command::FAILURE;
        }
        
        // 4. Obtener configuración (interactivo o flags)
        $config = $this->getConfiguration($input, $output, $helper);
        
        // 5. Mostrar resumen
        $this->showSummary($output, $config, $state);
        
        // 6. Confirmar
        $question = new ConfirmationQuestion('¿Continuar? (y/n): ', false);
        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Setup cancelado</comment>');
            return Command::SUCCESS;
        }
        
        // 7. Ejecutar setup
        return $this->runSetup($input, $output, $config, $state, $tutorialMode);
    }
    
    private function displayState(OutputInterface $output, array $state): void
    {
        $output->writeln('<fg=cyan>Estado Actual:</>');
        $output->writeln($this->formatStatus($state['is_bedrock'], 'Proyecto Bedrock detectado'));
        $output->writeln($this->formatStatus($state['docker_installed'], 'Docker Desktop instalado'));
        $output->writeln($this->formatStatus($state['docker_running'], 'Docker corriendo'));
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
    
    private function getConfiguration(InputInterface $input, OutputInterface $output, $helper): array
    {
        $output->writeln('<fg=cyan>Configuración de WordPress:</>');
        $output->writeln('');
        
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
        
        // Modo interactivo
        $url = $helper->ask($input, $output, new Question('URL del sitio [http://localhost:8080]: ', 'http://localhost:8080'));
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
