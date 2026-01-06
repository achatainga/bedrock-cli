<?php

namespace Roots\BedrockCli\Commands\Setup;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Services\ProjectValidationService;
use Roots\BedrockCli\Services\StateService;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class SetupCommand extends Command
{
    use ProjectSelectorTrait;
    
    private StateService $stateService;
    private DockerService $dockerService;
    private WpCliService $wpCliService;
    private ProjectValidationService $validationService;
    
    public function __construct(
        StateService $stateService,
        DockerService $dockerService,
        WpCliService $wpCliService,
        ProjectValidationService $validationService
    ) {
        $this->stateService = $stateService;
        $this->dockerService = $dockerService;
        $this->wpCliService = $wpCliService;
        $this->validationService = $validationService;
        parent::__construct();
    }
    
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
            ->addOption('skip-theme', null, InputOption::VALUE_NONE, 'Saltar activación de tema')
            ->addOption('skip-plugins', null, InputOption::VALUE_NONE, 'Saltar activación de plugins')
            ->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Tema a activar')
            ->addOption('plugins', null, InputOption::VALUE_REQUIRED, 'Plugins a activar (separados por coma)')
            ->addOption('tutorial', null, InputOption::VALUE_NONE, 'Modo tutorial (con explicaciones)')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Confirmar automáticamente (no interactivo)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $tutorialMode = $input->getOption('tutorial');
        
        // Header
        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Setup Automático - Bedrock CLI   </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        // [NUEVO] Asegurar permisos correctos
        $this->ensureComposerPermissions($output);
        $this->ensureUploadsPermissions($output);
        
        // 1. Detectar estado
        $output->writeln('<info>Detectando estado del proyecto...</info>');
        $output->writeln('');
        
        // Use injected services
        $stateService = $this->stateService;
        $validationService = $this->validationService;
        
        // Use unified validation logic
        $projectPath = getcwd();
        $dockerValidation = $validationService->validateDocker($projectPath);
        $dbValidation = $validationService->validateDatabase($projectPath);
        $wpValidation = $validationService->validateWordPress($projectPath);
        $acornValidation = $validationService->validateAcorn($projectPath);
        $inconsistencies = $validationService->detectInconsistencies($projectPath);
        
        $state = [
            'is_bedrock' => file_exists($projectPath . '/composer.json') && file_exists($projectPath . '/config/application.php'),
            'env_exists' => file_exists($projectPath . '/.env'),
            'docker_installed' => $dockerValidation->isValid || shell_exec('docker --version 2>/dev/null') !== null,
            'docker_running' => $dockerValidation->isValid,
            'containers_running' => $dockerValidation->isValid,
            'db_exists' => $dbValidation->isValid,
            'db_has_tables' => $wpValidation->isValid, // WordPress tables exist means DB has tables
            'wp_installed' => $wpValidation->isValid,
            'acorn_installed' => file_exists($projectPath . '/composer.json') && strpos(file_get_contents($projectPath . '/composer.json'), 'roots/acorn') !== false,
            'acorn_configured' => $acornValidation->isValid,
            'is_local_install' => true,
            'inconsistencies' => array_map(function($inc) {
                return [
                    'message' => $inc['message'],
                    'severity' => 'warning',
                    'files' => ['.env', 'docker-compose.yml']
                ];
            }, $inconsistencies),
            'pending_tasks' => $wpValidation->isValid ? [] : [[
                'name' => 'Instalar WordPress',
                'command' => 'bedrock setup',
                'severity' => 'critical'
            ]],
            'config' => $validationService->getProjectConfiguration($projectPath)
        ];
        
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
        if (!$input->getOption('yes')) {
            $question = new ConfirmationQuestion('¿Continuar? (y/n): ', false);
            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('<comment>Setup cancelado</comment>');
                return Command::SUCCESS;
            }
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
        
        $this->dockerService->up();
        
        sleep(5);
        
        return true;
    }
    
    private function getConfiguration(InputInterface $input, OutputInterface $output, $helper, array $state): array
    {
        // Leer configuración existente del .env
        $env = $state['config']['env'] ?? [];
        $docker = $state['config']['docker_compose'] ?? [];
        
        // Defaults desde archivos existentes
        // Leer puerto actual de docker-compose.yml directamente
        $dockerPort = $this->readDockerComposePort();
        $defaultUrl = $dockerPort ? "http://localhost:{$dockerPort}" : ($env['WP_HOME'] ?? 'http://localhost:8080');
        $defaultDbName = $env['DB_NAME'] ?? $docker['db_name'] ?? 'bedrock';
        
        // Si hay flags, usarlos
        if ($input->getOption('url')) {
            return [
                'url' => $this->validateAndFixUrl($input->getOption('url')),
                'title' => $input->getOption('title') ?? 'Mi Sitio',
                'adminUser' => $input->getOption('admin-user') ?? 'admin',
                'adminPassword' => $input->getOption('admin-password') ?? 'admin',
                'adminEmail' => $input->getOption('admin-email') ?? 'admin@example.com',
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
        
        // Obtener título actual de WordPress si existe
        $defaultTitle = 'Mi Sitio';
        if ($state['wp_installed']) {
            $docker = $this->dockerService;
            $wpcli = $this->wpCliService;
            $getTitleProcess = $wpcli->custom('option get blogname 2>/dev/null');
            $getTitleProcess->run();
            if ($getTitleProcess->isSuccessful() && !empty(trim($getTitleProcess->getOutput()))) {
                $defaultTitle = trim($getTitleProcess->getOutput());
            }
        }
        
        // Modo interactivo con defaults desde .env y WordPress
        $url = $helper->ask($input, $output, new Question("URL del sitio [{$defaultUrl}]: ", $defaultUrl));
        $url = $this->validateAndFixUrl($url);
        
        $title = $helper->ask($input, $output, new Question("Título del sitio [{$defaultTitle}]: ", $defaultTitle));
        $adminUser = $helper->ask($input, $output, new Question('Usuario admin [admin]: ', 'admin'));
        $adminPassword = $helper->ask($input, $output, new Question('Contraseña admin [admin]: ', 'admin'));
        $adminEmail = $helper->ask($input, $output, new Question('Email admin [admin@example.com]: ', 'admin@example.com'));
        
        // Selector de idioma
        $output->writeln('');
        $locales = [
            'en_US' => 'English (United States)',
            'es_ES' => 'Español (España)',
            'es_VE' => 'Español (Venezuela)',
            'es_MX' => 'Español (México)',
            'es_AR' => 'Español (Argentina)',
            'de_DE' => 'Deutsch (Deutschland)',
            'fr_FR' => 'Français (France)',
            'pt_BR' => 'Português (Brasil)'
        ];
        
        $localeQuestion = new \Symfony\Component\Console\Question\ChoiceQuestion(
            'Selecciona el idioma de WordPress:',
            array_values($locales),
            2  // Default: es_VE
        );
        $selectedLocaleName = $helper->ask($input, $output, $localeQuestion);
        $locale = array_search($selectedLocaleName, $locales);
        
        return [
            'url' => $url,
            'title' => $title,
            'adminUser' => $adminUser,
            'adminPassword' => $adminPassword,
            'adminEmail' => $adminEmail,
            'locale' => $locale
        ];
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
        $docker = $this->dockerService;
        $wpcli = $this->wpCliService;
        
        $output->writeln('');
        $output->writeln('<info>Ejecutando setup...</info>');
        $output->writeln('');
        
        // Paso 0: Actualizar archivos de configuración si cambió URL/puerto
        [$urlChanged, $oldUrl] = $this->updateConfigurationFiles($output, $config, $state);
        
        // Paso 1: Instalar WordPress
        if (!$state['wp_installed'] && !$input->getOption('skip-wp-install')) {
            $locale = $config['locale'] ?? 'es_VE';
            
            // Descargar idioma ANTES de instalar WordPress
            if ($locale !== 'en_US') {
                $output->writeln("<comment>Descargando idioma {$locale}...</comment>");
                $downloadLang = $wpcli->custom("language core download {$locale}");
                $downloadLang->run();
            }
            
            $output->writeln('<comment>Instalando WordPress...</comment>');            
            $process = $wpcli->coreInstall([
                'url' => $config['url'],
                'title' => $config['title'],
                'admin_user' => $config['adminUser'],
                'admin_password' => $config['adminPassword'],
                'admin_email' => $config['adminEmail'],
                'locale' => $locale
            ]);
            
            $this->runWithLoader($process, $output, 'Instalando WordPress');
            
            // Fix: Forzar actualización de URLs en BD para sobrescribir valores cacheados por Docker
            if ($process->isSuccessful()) {
                $cleanUrl = rtrim($config['url'], '/');
                $wpcli->custom("option update home '{$cleanUrl}'")->run();
                $wpcli->custom("option update siteurl '{$cleanUrl}/wp'")->run();
                
                // Activar idioma descargado
                if ($locale !== 'en_US') {
                    $wpcli->custom("site switch-language {$locale}")->run();
                }
            }
            
            if (!$process->isSuccessful()) {
                $output->writeln('<error>✗ Error al instalar WordPress</error>');
                $output->writeln('');
                $output->writeln('<fg=yellow>Output del comando:</>');
                $output->writeln($process->getOutput());
                if ($process->getErrorOutput()) {
                    $output->writeln('<fg=yellow>Errores:</>');
                    $output->writeln($process->getErrorOutput());
                }
                $output->writeln('');
                $output->writeln('<fg=cyan>Diagnóstico:</>');
                $output->writeln('  1. Verifica que Docker esté corriendo: docker ps');
                $output->writeln('  2. Verifica que la BD esté accesible: docker-compose exec web wp db check');
                $output->writeln('  3. Verifica credenciales en .env (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD)');
                $output->writeln('');
                return Command::FAILURE;
            }
            
            $output->writeln('<info>✓ WordPress instalado</info>');
        }
        
        // Paso 1.5: Si WordPress ya existe y cambió URL o credenciales, actualizar en BD
        if ($state['wp_installed']) {
            if ($urlChanged) {
                $output->writeln('');
                $output->writeln('<comment>Actualizando URL en WordPress...</comment>');
                
                // Usar search-replace para actualizar URLs en toda la BD
                $process = $wpcli->custom("search-replace '{$oldUrl}' '{$config['url']}' --all-tables --skip-columns=guid");
                $this->runWithLoader($process, $output, 'Reemplazando URLs en BD');
                
                $output->writeln('<info>✓ URL actualizada en WordPress</info>');
            }
            
            // Actualizar o crear usuario admin
            $output->writeln('');
            $output->writeln('<comment>Configurando usuario admin...</comment>');
            
            // Get table prefix from env
            $projectPath = getcwd();
            $env = $this->validationService->readEnvFile($projectPath);
            $prefix = $env['DB_PREFIX'] ?? 'wp_';
            
            // Verificar si usuario existe
            $checkUser = $wpcli->custom("user get {$config['adminUser']} --field=ID 2>/dev/null");
            $checkUser->run();
            
            if ($checkUser->isSuccessful()) {
                // Usuario existe, actualizar contraseña
                $process = $wpcli->custom("user update {$config['adminUser']} --user_pass='{$config['adminPassword']}' --user_email='{$config['adminEmail']}' --skip-email");
                $this->runWithLoader($process, $output, 'Actualizando credenciales');
            } else {
                // Usuario no existe, modificar usuario ID 1 usando wp user update
                $process = $wpcli->custom("user update 1 --user_login='{$config['adminUser']}' --user_nicename='{$config['adminUser']}' --user_pass='{$config['adminPassword']}' --user_email='{$config['adminEmail']}' --skip-email");
                $this->runWithLoader($process, $output, 'Configurando usuario admin (ID 1)');
            }
            
            $output->writeln('<info>✓ Usuario admin configurado</info>');
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
            
            // Ajustar permisos de cache
            $output->writeln('<comment>Ajustando permisos de cache...</comment>');
            $process = Process::fromShellCommandline('docker-compose exec -T web chown -R www-data:www-data /var/www/html/web/app/cache');
            $process->run();
            $process = Process::fromShellCommandline('docker-compose exec -T web chmod -R 755 /var/www/html/web/app/cache');
            $process->run();
            
            $output->writeln('<info>✓ Acorn configurado</info>');
        }
        
        // Paso 3: Activar tema
        if (!$input->getOption('skip-theme')) {
            $helper = $this->getHelper('question');
            $this->setupTheme($input, $output, $wpcli, $helper);
        }
        
        // Paso 4: Activar plugins
        if (!$input->getOption('skip-plugins')) {
            $helper = $this->getHelper('question');
            $this->setupPlugins($input, $output, $wpcli, $helper);
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
        
        // Marcar paso 2 del wizard como completado
        $this->stateService->markStepCompleted(2);
        
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
    
    private function updateConfigurationFiles(OutputInterface $output, array $config, array $state): array
    {
        $env = $state['config']['env'] ?? [];
        $docker = $state['config']['docker_compose'] ?? [];
        
        // BUG FIX 1: Corregir inconsistencias de puertos antes de continuar
        if (!empty($state['inconsistencies'])) {
            $this->fixPortInconsistencies($output, $state['inconsistencies']);
        }
        
        $currentUrl = $env['WP_HOME'] ?? '';
        $newUrl = $config['url'];
        
        // Detectar si cambió URL
        if ($currentUrl === $newUrl) {
            return [false, $currentUrl]; // No cambió
        }
        
        $output->writeln('<comment>Detectado cambio de URL...</comment>');
        $output->writeln("  Anterior: <fg=white>{$currentUrl}</>");
        $output->writeln("  Nueva: <fg=white>{$newUrl}</>");
        $output->writeln('');
        
        // Leer puerto actual de docker-compose.yml
        $currentPort = $this->readDockerComposePort();
        
        // Extraer puerto de URL (si tiene puerto explícito)
        $newPort = $this->extractPort($newUrl);
        
        // Si URL no tiene puerto explícito, mantener puerto actual
        if (!$this->hasExplicitPort($newUrl)) {
            $newPort = $currentPort;
        }
        
        // Actualizar .env
        $this->updateEnvFile($newUrl);
        $output->writeln('<info>✓ .env actualizado</info>');
        
        // Si cambió la URL (host o puerto), necesitamos reiniciar para que Docker lea el nuevo .env
        if ($currentUrl !== $newUrl || $newPort !== $currentPort) {
            if ($newPort !== $currentPort) {
                $output->writeln("<comment>Puerto cambió de {$currentPort} a {$newPort}</comment>");
                $this->updateDockerComposePort($newPort);
                $output->writeln('<info>✓ docker-compose.yml actualizado</info>');
            }
            
            // Reconstruir contenedores para que Docker lea el nuevo .env
            $output->writeln('<comment>Reiniciando contenedores para aplicar cambios de URL...</comment>');
            $dockerService = $this->dockerService;
            
            $process = $dockerService->down();
            $this->runWithLoader($process, $output, 'Deteniendo contenedores');
            
            sleep(2);
            
            $process = $dockerService->up();
            $this->runWithLoader($process, $output, 'Iniciando contenedores');
            
            sleep(5); // Dar tiempo a MySQL para arrancar
            $output->writeln('<info>✓ Contenedores reiniciados con nueva configuración</info>');
        } else {
            $output->writeln("<info>Puerto actual: {$currentPort} (sin cambios)</info>");
            $output->writeln('<info>✓ docker-compose.yml sin cambios</info>');
        }
        
        $output->writeln('');
        return [true, $currentUrl]; // URL cambió
    }
    
    private function validateAndFixUrl(string $input): string
    {
        $input = trim($input);
        
        // Si es solo número, asumir localhost
        if (preg_match('/^\d+$/', $input)) {
            $port = (int)$input;
            return $port === 80 ? 'http://localhost' : "http://localhost:{$input}";
        }
        
        // Si no tiene protocolo, agregar http://
        if (!preg_match('/^https?:\/\//', $input)) {
            return "http://{$input}";
        }
        
        return $input;
    }
    
    private function extractPort(string $url): int
    {
        // Si es solo número, retornarlo
        if (preg_match('/^\d+$/', $url)) {
            return (int)$url;
        }
        
        // Buscar :PUERTO en URL
        if (preg_match('/:(\d+)/', $url, $matches)) {
            return (int)$matches[1];
        }
        
        // Si es https, puerto 443, si es http, puerto 80
        return str_starts_with($url, 'https://') ? 443 : 80;
    }
    
    private function hasExplicitPort(string $url): bool
    {
        // Verificar si URL tiene puerto explícito :PUERTO
        return preg_match('/:\d+/', $url) === 1;
    }
    
    private function readDockerComposePort(): int
    {
        // PRIORIDAD 1: Leer de .env (fuente de verdad)
        $envPath = getcwd() . '/.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);
            
            // Buscar WP_PORT
            if (preg_match('/^WP_PORT=(\d+)/m', $envContent, $matches)) {
                return (int)$matches[1];
            }
            
            // Buscar puerto en WP_HOME
            if (preg_match('/WP_HOME=.*:(\d+)/m', $envContent, $matches)) {
                return (int)$matches[1];
            }
        }
        
        // PRIORIDAD 2: Leer de docker-compose.yml (solo modo full)
        $dockerPath = getcwd() . '/docker-compose.yml';
        if (file_exists($dockerPath)) {
            $content = file_get_contents($dockerPath);
            // Buscar puerto en formato "PUERTO:80"
            if (preg_match('/-\s*"(\d+):80"/', $content, $matches)) {
                return (int)$matches[1];
            }
        }
        
        // FALLBACK
        return 80;
    }
    
    private function updateEnvFile(string $newUrl): void
    {
        $envPath = getcwd() . '/.env';
        if (!file_exists($envPath)) {
            return;
        }
        
        $content = file_get_contents($envPath);
        // Reemplazar toda la línea WP_HOME, no solo el valor
        // Asegurar que no guardamos :80 en el .env
        $cleanUrl = preg_replace('/:(80|443)$/', '', $newUrl);

        $content = preg_replace(
            "/^WP_HOME=.*/m",
            "WP_HOME=\"{$cleanUrl}\"",
            $content
        );
        
        // También actualizar APP_URL (Acorn)
        $content = preg_replace(
            "/^APP_URL=.*/m",
            "APP_URL=\"{$cleanUrl}\"",
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
    
    private function setupTheme(InputInterface $input, OutputInterface $output, WpCliService $wpcli, $helper): void
    {
        $output->writeln('');
        $output->writeln('<comment>Configurando tema...</comment>');
        
        // Si hay flag --theme, usarlo
        if ($input->getOption('theme')) {
            $theme = $input->getOption('theme');
        } else {
            // Listar temas disponibles
            $themesDir = getcwd() . '/web/app/themes';
            if (!is_dir($themesDir)) {
                $output->writeln('<comment>No hay temas disponibles</comment>');
                return;
            }
            
            $themes = array_filter(scandir($themesDir), function($item) use ($themesDir) {
                return $item !== '.' && $item !== '..' && is_dir($themesDir . '/' . $item);
            });
            
            if (empty($themes)) {
                $output->writeln('<comment>No hay temas disponibles</comment>');
                return;
            }
            
            $themes = array_values($themes);
            
            $output->writeln('<fg=cyan>Temas disponibles:</>');
            foreach ($themes as $idx => $t) {
                $output->writeln("  " . ($idx + 1) . ". {$t}");
            }
            $output->writeln('');
            
            $question = new Question('¿Qué tema deseas activar? (nombre o número): ');
            $answer = $helper->ask($input, $output, $question);
            
            if (is_numeric($answer)) {
                $theme = $themes[(int)$answer - 1] ?? null;
            } else {
                $theme = $answer;
            }
        }
        
        if ($theme) {
            $process = $wpcli->custom("theme activate {$theme}");
            $this->runWithLoader($process, $output, "Activando tema {$theme}");
            
            if ($process->isSuccessful()) {
                $output->writeln("<info>✓ Tema '{$theme}' activado</info>");
            }
        }
    }
    
    private function setupPlugins(InputInterface $input, OutputInterface $output, WpCliService $wpcli, $helper): void
    {
        $output->writeln('');
        $output->writeln('<comment>Configurando plugins...</comment>');
        
        if ($input->getOption('plugins')) {
            $pluginsToActivate = explode(',', $input->getOption('plugins'));
        } else {
            $pluginService = new \Roots\BedrockCli\Services\PluginActivationService($wpcli);
            $projectRoot = getcwd();
            $plugins = $pluginService->getAvailablePlugins($projectRoot);
            
            if (empty($plugins)) {
                $output->writeln('<comment>No hay plugins disponibles</comment>');
                return;
            }
            
            $output->writeln('<fg=cyan>Plugins disponibles:</>');
            foreach ($plugins as $idx => $p) {
                $output->writeln("  " . ($idx + 1) . ". {$p}");
            }
            $output->writeln('');
            
            $question = new Question('¿Qué plugins deseas activar? (números separados por coma, o Enter para omitir): ', '');
            $answer = $helper->ask($input, $output, $question);
            
            if (empty($answer)) {
                return;
            }
            
            $pluginsToActivate = $pluginService->parsePluginSelection($answer, $plugins);
        }
        
        foreach ($pluginsToActivate as $plugin) {
            if (empty($plugin)) continue;
            
            $process = $wpcli->custom("plugin activate {$plugin}");
            $this->runWithLoader($process, $output, "Activando plugin {$plugin}");
            
            if ($process->isSuccessful()) {
                // Verificar si realmente se activó
                $checkProcess = $wpcli->custom("plugin is-active {$plugin}");
                $checkProcess->run();
                
                if ($checkProcess->isSuccessful()) {
                    $output->writeln("<info>✓ Plugin '{$plugin}' activado</info>");
                } else {
                    $output->writeln("<error>✗ Plugin '{$plugin}' falló al activar</error>");
                    // Mostrar error si existe
                    $errorOutput = trim($process->getErrorOutput());
                    if (!empty($errorOutput)) {
                        $output->writeln("<comment>  Error: {$errorOutput}</comment>");
                    }
                }
            } else {
                $output->writeln("<error>✗ Plugin '{$plugin}' falló al activar</error>");
                $errorOutput = trim($process->getErrorOutput());
                if (!empty($errorOutput)) {
                    $output->writeln("<comment>  Error: {$errorOutput}</comment>");
                }
            }
        }
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
    
    private function fixPortInconsistencies(OutputInterface $output, array $inconsistencies): void
    {
        $envPath = getcwd() . '/.env';
        if (!file_exists($envPath)) {
            return;
        }
        
        $envContent = file_get_contents($envPath);
        $modified = false;
        
        foreach ($inconsistencies as $issue) {
            // Detectar inconsistencia de DB_PORT
            if (strpos($issue['message'], 'DB_PORT missing') !== false) {
                // Extraer puerto de mensaje (formato: "Docker MySQL uses port 3307")
                if (preg_match('/port (\d+)/', $issue['message'], $matches)) {
                    $dbPort = $matches[1];
                    
                    // Agregar DB_PORT al .env si no existe
                    if (strpos($envContent, 'DB_PORT=') === false) {
                        // Buscar línea DB_HOST para insertar después
                        if (preg_match('/(DB_HOST=.*)/', $envContent, $hostMatch)) {
                            $envContent = str_replace(
                                $hostMatch[1],
                                $hostMatch[1] . "\nDB_PORT={$dbPort}",
                                $envContent
                            );
                            $modified = true;
                            $output->writeln("<info>✓ DB_PORT={$dbPort} agregado al .env</info>");
                        }
                    }
                }
            }
        }
        
        if ($modified) {
            file_put_contents($envPath, $envContent);
        }
    }
    
    /**
     * Asegura permisos correctos en el directorio actual (getcwd)
     */
    private function ensureComposerPermissions(OutputInterface $output): void
    {
        $projectRoot = getcwd();
        $needsAdjustment = false;

        // Comprobación rápida
        if (file_exists("{$projectRoot}/composer.json")) {
            $perms = fileperms("{$projectRoot}/composer.json") & 0777;
            if ($perms !== 0666 && $perms !== 0777) {
                $needsAdjustment = true;
            }
        }

        if ($needsAdjustment) {
            $output->writeln('<info>Corrigiendo permisos de archivos...</info>');
            
            // Files -> 666
            $files = ["composer.json", "composer.lock"];
            foreach ($files as $file) {
                if (file_exists($projectRoot . '/' . $file)) {
                    @chmod($projectRoot . '/' . $file, 0666);
                }
            }

            // Dirs -> 777
            $dirs = ["web/app/plugins"];
            foreach ($dirs as $dir) {
                if (is_dir($projectRoot . '/' . $dir)) {
                    @chmod($projectRoot . '/' . $dir, 0777);
                }
            }

            // Vendor Recursivo -> 777
            if (is_dir($projectRoot . '/vendor/composer')) {
                $process = Process::fromShellCommandline('chmod -R 777 vendor/composer');
                $process->run();
            }
            
            $output->writeln('  ✓ Permisos ajustados para gestión web');
            $output->writeln('');
        }
    }
    
    /**
     * Asegura permisos correctos para uploads en el directorio actual
     */
    private function ensureUploadsPermissions(OutputInterface $output): void
    {
        $projectRoot = getcwd();
        $uploadsDir = $projectRoot . '/web/app/uploads';
        
        // Crear directorio si no existe
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0755, true);
        }
        
        // Verificar si necesita ajuste de permisos
        $needsAdjustment = false;
        if (is_dir($uploadsDir)) {
            $perms = fileperms($uploadsDir) & 0777;
            $stat = stat($uploadsDir);
            // Verificar si no es propiedad de www-data (uid 33) o no tiene permisos 755
            if ($stat['uid'] !== 33 || $perms < 0755) {
                $needsAdjustment = true;
            }
        }
        
        if ($needsAdjustment) {
            $output->writeln('<info>Configurando permisos para uploads...</info>');
            
            // Intentar cambiar owner a www-data (Docker)
            $process = Process::fromShellCommandline("chown -R 33:33 \"{$uploadsDir}\" 2>/dev/null");
            $process->run();
            
            // Configurar permisos
            @chmod($uploadsDir, 0755);
            
            // Si hay subdirectorios, aplicar recursivamente
            $process = Process::fromShellCommandline("find \"{$uploadsDir}\" -type d -exec chmod 755 {} \; 2>/dev/null");
            $process->run();
            
            $process = Process::fromShellCommandline("find \"{$uploadsDir}\" -type f -exec chmod 644 {} \; 2>/dev/null");
            $process->run();
            
            $output->writeln('  ✓ Permisos de uploads configurados');
            $output->writeln('');
        }
    }
}
