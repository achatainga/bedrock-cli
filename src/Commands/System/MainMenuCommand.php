<?php

namespace Roots\BedrockCli\Commands\System;

use Roots\BedrockCli\Services\PremiumRepoService;
use Roots\BedrockCli\Services\StateService;
use Roots\BedrockCli\Services\ProjectValidationService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class MainMenuCommand extends Command
{
    use ProjectSelectorTrait;
    private StateService $stateService;
    private PremiumRepoService $premiumRepoService;
    private ProjectValidationService $validationService;
    private bool $isWindows;

    public function __construct(StateService $stateService, PremiumRepoService $premiumRepoService, ProjectValidationService $validationService)
    {
        parent::__construct();
        $this->stateService = $stateService;
        $this->premiumRepoService = $premiumRepoService;
        $this->validationService = $validationService;
        $this->isWindows = PHP_OS_FAMILY === 'Windows';
    }

    protected function configure(): void
    {
        $this->setName('menu')
             ->setDescription('Abre el menú interactivo')
             ->addOption('guia', 'g', InputOption::VALUE_NONE, 'Modo guía simplificado para principiantes')
             ->addOption('skip-validation', 's', InputOption::VALUE_NONE, 'Omitir validaciones para carga rápida');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // NUEVO: Activar ProjectSelectorTrait para modo guía
        if ($input->getOption('guia')) {
            if (!$this->ensureBedrockProject($input, $output)) {
                return Command::FAILURE;
            }
        }
        
        $helper = $this->getHelper('question');
        
        // Cargar wizard state si existe
        $state = $this->stateService->loadState(getcwd());
        
        // FASE 2.5: Validaciones con animación y cache
        $skipValidation = $input->getOption('skip-validation');
        
        if ($state && $state['wizard_mode'] && !$skipValidation) {
            $this->showValidationProgress($output);
            $this->stateService->updateStepValidations(getcwd());
            // Recargar estado después de validaciones
            $state = $this->stateService->loadState(getcwd());
        }
        
        $currentStep = $state && $state['wizard_mode'] ? $this->stateService->getCurrentStep($state) : null;
        
        // FASE 2: Verificar si está en modo guía
        $isGuidedMode = $input->getOption('guia');
        
        // DEBUG: Mostrar información de modo guía
        if ($isGuidedMode) {
            $output->writeln('<comment>DEBUG: Modo guía activado</comment>');
            $output->writeln('<comment>DEBUG: currentStep = ' . ($currentStep ? 'EXISTS' : 'NULL') . '</comment>');
            $output->writeln('<comment>DEBUG: state = ' . ($state ? 'EXISTS' : 'NULL') . '</comment>');
            if ($state) {
                $output->writeln('<comment>DEBUG: wizard_mode = ' . ($state['wizard_mode'] ?? 'NOT_SET') . '</comment>');
            }
        }
        
        // FASE 2.6: Verificar acceso a repo premium (con skip-validation)
        $needsAuth = false;
        if (!$skipValidation) {
            $externalServices = $this->stateService->validateExternalServices();
            $needsAuth = !$externalServices['premium_repo'];
        }
        
        while (true) {
            if ($isGuidedMode && $currentStep) {
                // Pasar flag de skip validation al modo guía
                return $this->showGuidedMenu($input, $output, $helper, $currentStep, $needsAuth, $skipValidation);
            } elseif ($isGuidedMode) {
                $output->writeln('<info>Modo guía solicitado pero no hay wizard activo. Mostrando menú normal.</info>');
            }
            $output->writeln('');
            // Usar caracteres ASCII en Windows para evitar artefactos visuales
            $boxTopLeft = $this->isWindows ? '+' : '╔';
            $boxTopRight = $this->isWindows ? '+' : '╗';
            $boxBottomLeft = $this->isWindows ? '+' : '╚';
            $boxBottomRight = $this->isWindows ? '+' : '╝';
            $boxHorizontal = $this->isWindows ? '-' : '═';
            $boxVertical = $this->isWindows ? '|' : '║';
            
            $output->writeln('<fg=cyan;options=bold>' . $boxTopLeft . str_repeat($boxHorizontal, 39) . $boxTopRight . '</>');
            $output->writeln('<fg=cyan;options=bold>' . $boxVertical . '</> <fg=yellow;options=bold>      BEDROCK CLI v2.0          </> <fg=cyan;options=bold>     ' . $boxVertical . '</>');
            $output->writeln('<fg=cyan;options=bold>' . $boxBottomLeft . str_repeat($boxHorizontal, 39) . $boxBottomRight . '</>');
            $output->writeln('');
            
            // Mostrar wizard si está activo
            if ($currentStep) {
                // BUG FIX 2: No mostrar paso si ya está completado
                if (!($currentStep['completed'] ?? false)) {
                    $output->writeln('<fg=yellow;options=bold>📋 PASO ' . $currentStep['id'] . ': ' . $currentStep['title'] . '</>');
                    $output->writeln('<comment>' . $currentStep['description'] . '</comment>');
                    $output->writeln('');
                }
            }
            
            $output->writeln('<fg=yellow>🚀 INICIO RÁPIDO</>');
            $this->printMenuItem($output, 'N', 'N', '🆕 New      - Crear proyecto desde cero', $currentStep);
            $this->printMenuItem($output, '1', '1', '🩺 Doctor   - Verificar dependencias', $currentStep);
            $this->printMenuItem($output, '2', '2', '⚙️  Setup    - Configuración inicial', $currentStep);
            $this->printMenuItem($output, '3', '3', '📋 Profiles - Crear/gestionar profiles', $currentStep);
            $output->writeln('');
            
            $output->writeln('<fg=green>⚡ DESARROLLO</>');
            $this->printMenuItem($output, '4', 'D', '🐳 Docker   - Levantar/bajar contenedores', $currentStep);
            $this->printMenuItem($output, '5', 'M', '🎛️  Manage   - Plugins, Themes, Dependencies', $currentStep);
            $this->printMenuItem($output, '6', 'B', '🗄️  Database - Gestión de base de datos', $currentStep);
            $output->writeln('');
            
            $output->writeln('<fg=cyan>🔍 CONTENIDO</>');
            $this->printMenuItem($output, '7', '7', '🔍 Search   - Buscar en WordPress.org', $currentStep);
            $this->printMenuItem($output, '8', '8', 'ℹ️  Info     - Estado del proyecto', $currentStep);
            $output->writeln('');
            
            $output->writeln('<fg=magenta>🔧 AVANZADO</>');
            $this->printMenuItem($output, '9', '9', '🚀 Init     - Inicializar ambiente', $currentStep);
            $this->printMenuItem($output, 'I', 'I', '🤖 AI       - Copiloto inteligente', $currentStep);
            $output->writeln('');
            
            $this->printMenuItem($output, 'O', 'O', '⚙️  Options   - Gestión de wp_options', $currentStep);
            $this->printMenuItem($output, 'A', 'A', '🌱 Acorn    - Roots Acorn', $currentStep);
            
            if ($needsAuth) {
                $output->writeln(' <fg=red>[T]</> 🔐 Auth     - ⚠️  CONFIGURAR CREDENCIALES');
            } else {
                $this->printMenuItem($output, 'T', 'T', '🔐 Auth     - Credenciales repos privados', $currentStep);
            }
            $this->printMenuItem($output, 'B', 'B', '💾 Backup   - Crear backup', $currentStep);
            $this->printMenuItem($output, 'R', 'R', '🗑️  Reinstall - Reinstalar (DESTRUCTIVO)', $currentStep);
            $output->writeln('');
            
            $this->printMenuItem($output, '0', '0', '❌ Salir', $currentStep);
            $output->writeln('');

            $question = new Question('<fg=yellow>Opción [0-9, N, I, O, A, T, B, R]:</> ', '0');
            $selectedIndex = $helper->ask($input, $output, $question);
            
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            $selectedIndex = strtoupper($selectedIndex);
            
            $validOptions = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'N', 'I', 'O', 'A', 'T', 'B', 'R'];
            if (!in_array($selectedIndex, $validOptions)) {
                $output->writeln('<error>Opción inválida. Usa 0-9, N, I, O, A, T, B, R.</error>');
                sleep(1);
                continue;
            }
            
            if ($selectedIndex === '0') {
                $output->writeln('');
                $output->writeln('<info>Hasta luego!</info>');
                return Command::SUCCESS;
            }

            $commandMap = [
                'N' => 'new:wizard',
                '1' => 'doctor',
                '2' => 'setup',
                '3' => 'profile:menu',
                '4' => 'docker',
                '5' => 'manage',
                '6' => 'db',
                '7' => 'search:menu',
                '8' => 'info',
                '9' => 'init:menu',
                'I' => 'ai',
                'O' => 'options',
                'A' => 'acorn',
                'T' => 'auth:menu',
                'B' => 'backup',
                'R' => 'reinstall',
            ];

            $commandName = $commandMap[$selectedIndex];
            if ($commandName) {
                $output->writeln('');
                $command = $this->getApplication()->find($commandName);
                // FASE 3: Fix propagación de flags - crear input limpio
                $cleanInput = new ArrayInput([]);
                $command->run($cleanInput, $output);
            }
        }

        return Command::SUCCESS;
    }

    private function checkPremiumRepoAccess(string $repoUrl): bool
    {
        try {
            $result = $this->premiumRepoService->checkAccess($repoUrl);
            return $result['needs_auth'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function printMenuItem(OutputInterface $output, string $key, string $menuItem, string $label, ?array $currentStep): void
    {
        $isActive = $currentStep && $currentStep['menu_item'] === $menuItem;
        if ($isActive) {
            $output->writeln(" <fg=green>[{$key}] {$label}</>");
        } else {
            $output->writeln(" <fg=cyan>[{$key}]</> {$label}");
        }
    }

    // ===== FASE 2.5: ANIMACIÓN DE VALIDACIONES =====
    
    private function showValidationProgress(OutputInterface $output): void
    {
        $output->writeln('<fg=cyan>🔍 Evaluando estado del proyecto...</>');
        
        $projectPath = getcwd();
        $isDockerProject = file_exists($projectPath . '/docker-compose.yml');
        
        $validations = [
            'Profile configuration' => ['delay' => 0.2, 'method' => 'validateProfileExists'],
            'VCS access (GitHub/GitLab)' => ['delay' => 0.4, 'method' => 'validateVcsAccess'],
            ($isDockerProject ? 'Docker containers' : 'Project mode') => ['delay' => 0.3, 'method' => 'validateDockerRunning'],
            'Database connection' => ['delay' => 0.4, 'method' => 'validateDatabaseAccess'],
            'WordPress installation' => ['delay' => 0.5, 'method' => 'validateWordPressInstalled'],
            'Theme activation' => ['delay' => 0.3, 'method' => 'validateThemeActive'],
            'Plugins status' => ['delay' => 0.2, 'method' => 'validatePluginsActive'],
            'Acorn setup' => ['delay' => 0.3, 'method' => 'validateAcornConfigured']
        ];
        
        foreach ($validations as $task => $config) {
            $output->write("  • Evaluando {$task}...");
            usleep((int)($config['delay'] * 1000000));
            
            // Ejecutar validación real
            $result = false;
            switch ($config['method']) {
                case 'validateProfileExists':
                    $result = $this->stateService->validateProfileExists($projectPath);
                    break;
                case 'validateVcsAccess':
                    $result = $this->stateService->validateVcsAccess($projectPath);
                    break;
                case 'validateDockerRunning':
                    $result = $this->stateService->validateDockerRunning($projectPath);
                    break;
                case 'validateDatabaseAccess':
                    $result = $this->stateService->validateDatabaseAccess($projectPath);
                    break;
                case 'validateWordPressInstalled':
                    $result = $this->stateService->validateWordPressInstalled($projectPath);
                    break;
                case 'validateThemeActive':
                    // Get actual theme from profile
                    $profileFile = $projectPath . '/.bedrock/profile.json';
                    $themeName = 'twentytwentyfive'; // default
                    if (file_exists($profileFile)) {
                        $profile = json_decode(file_get_contents($profileFile), true);
                        if (isset($profile['themes'][0]['name'])) {
                            $themeName = $profile['themes'][0]['name'];
                        }
                    }
                    $result = $this->stateService->validateThemeActive($projectPath, $themeName);
                    break;
                case 'validatePluginsActive':
                    $result = $this->stateService->validatePluginsActive($projectPath);
                    break;
                case 'validateAcornConfigured':
                    $acornValidation = $this->validationService->validateAcorn($projectPath);
                    $result = $acornValidation->isValid;
                    break;
            }
            
            // Special handling for Acorn to show granular status
            if ($config['method'] === 'validateAcornConfigured') {
                $acornValidation = $this->validationService->validateAcorn($projectPath);
                
                if ($acornValidation->isValid) {
                    $output->writeln(' <fg=green>✓ Fully configured</>');
                } else {
                    $output->writeln(' <fg=yellow>⚠ ' . $acornValidation->message . '</>');
                }
            } else {
                if ($result) {
                    $output->writeln(' <fg=green>✓</>');
                } else {
                    $output->writeln(' <fg=red>❌</>');
                }
            }
        }
        
        $output->writeln('');
    }

    // ===== FASE 2: MODO GUÍA SIMPLIFICADO =====

    private function showGuidedMenu(InputInterface $input, OutputInterface $output, $helper, array $currentStep, bool $needsAuth, bool $skipValidation = false): int
    {
        while (true) {
            $output->writeln('');
            // Usar caracteres ASCII en Windows para evitar artefactos visuales
            $boxTopLeft = $this->isWindows ? '+' : '╔';
            $boxTopRight = $this->isWindows ? '+' : '╗';
            $boxBottomLeft = $this->isWindows ? '+' : '╚';
            $boxBottomRight = $this->isWindows ? '+' : '╝';
            $boxHorizontal = $this->isWindows ? '-' : '═';
            $boxVertical = $this->isWindows ? '|' : '║';
            
            $output->writeln('<fg=cyan;options=bold>' . $boxTopLeft . str_repeat($boxHorizontal, 39) . $boxTopRight . '</>');
            $output->writeln('<fg=cyan;options=bold>' . $boxVertical . '</> <fg=yellow;options=bold>    BEDROCK CLI - MODO GUÍA    </> <fg=cyan;options=bold>' . $boxVertical . '</>');
            $output->writeln('<fg=cyan;options=bold>' . $boxBottomLeft . str_repeat($boxHorizontal, 39) . $boxBottomRight . '</>');
            $output->writeln('');
            
            // Mostrar progreso
            $this->showProgressIndicator($output, $currentStep);
            $output->writeln('');
            
            // Mostrar inconsistencias si existen
            $this->showInconsistencies($output);
            
            // Mostrar paso actual destacado
            $output->writeln('<fg=yellow;options=bold>📝 PASO ACTUAL: ' . $currentStep['title'] . '</>');
            $output->writeln('<comment>' . $currentStep['description'] . '</comment>');
            $output->writeln('');
            
            // Comando copy-paste destacado
            if (isset($currentStep['command'])) {
                $output->writeln('<fg=green;options=bold>📝 COMANDO PARA EJECUTAR:</>');
                $output->writeln('<info>' . $currentStep['command'] . '</info>');
                $output->writeln('');
            }
            
            // Opciones simplificadas
            $output->writeln('<fg=cyan>🎯 OPCIONES:</>');
            
            // Opción principal del paso actual
            $mainOption = $this->getMainOptionForStep($currentStep);
            if ($mainOption) {
                $output->writeln(" <fg=green;options=bold>[{$mainOption['key']}]</> {$mainOption['label']}");
            }
            
            // Opción de reparación si hay inconsistencias
            $hasInconsistencies = $this->hasInconsistencies();
            if ($hasInconsistencies) {
                $output->writeln(' <fg=yellow;options=bold>[F]</> 🔧 Fix      - Reparar inconsistencias automáticamente');
            }
            
            // Opciones esenciales
            $output->writeln(' <fg=cyan>[8]</> ℹ️  Info     - Estado del proyecto');
            $output->writeln(' <fg=cyan>[M]</> 📝 Menú    - Ver menú completo');
            $output->writeln(' <fg=cyan>[0]</> ❌ Salir');
            $output->writeln('');

            $options = ($mainOption ? $mainOption['key'] . ', ' : '') . ($hasInconsistencies ? 'F, ' : '') . '8, M, 0';
            $question = new Question('<fg=yellow>Opción [' . $options . ']:</> ', '0');
            $selectedIndex = $helper->ask($input, $output, $question);
            
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            $selectedIndex = strtoupper($selectedIndex);
            
            if ($selectedIndex === '0') {
                $output->writeln('');
                $output->writeln('<info>Hasta luego!</info>');
                return Command::SUCCESS;
            }
            
            if ($selectedIndex === 'M') {
                // Cambiar a menú completo
                return $this->showFullMenu($input, $output, $helper, $needsAuth);
            }
            
            if ($selectedIndex === 'F' && $hasInconsistencies) {
                // Reparar inconsistencias
                $this->fixInconsistencies($output);
                continue;
            }
            
            // Ejecutar comando seleccionado
            $commandMap = [
                '1' => 'doctor',
                '2' => 'setup', 
                '4' => 'docker',
                '8' => 'info',
                'D' => 'docker',
                'P' => 'plugins:activate',
                'T' => 'themes:menu',
                'A' => 'acorn',
                'S' => 'seed'
            ];

            // Ejecutar comando seleccionado
            $commandMap = [
                '1' => 'doctor',
                '2' => 'setup', 
                '4' => 'docker',
                '8' => 'info',
                'D' => 'docker',
                'P' => 'plugins:activate-multiple',
                'T' => 'themes:menu',
                'A' => 'acorn',
                'S' => 'seed'
            ];

            $commandName = $commandMap[$selectedIndex] ?? null;
            if ($commandName) {
                $output->writeln('');
                $command = $this->getApplication()->find($commandName);
                $cleanInput = new ArrayInput([]);
                $command->run($cleanInput, $output);
                
                // Recargar estado después de ejecutar comando
                if (!$skipValidation) {
                    $output->writeln('<fg=cyan>🔄 Actualizando estado...</>');
                    $this->stateService->updateStepValidations(getcwd());
                }
                $state = $this->stateService->loadState(getcwd());
                $currentStep = $state && $state['wizard_mode'] ? $this->stateService->getCurrentStep($state) : null;
                
                if (!$currentStep) {
                    $output->writeln('<info>🎉 ¡Todos los pasos completados! El proyecto está listo.</info>');
                    return Command::SUCCESS;
                }
                
                // Continue loop to show menu again
                continue;
            } else {
                $output->writeln('<error>Opción inválida.</error>');
                sleep(1);
            }
        }
    }
    
    private function showProgressIndicator(OutputInterface $output, array $currentStep): void
    {
        // Obtener estado completo para calcular progreso
        $state = $this->stateService->loadState(getcwd());
        
        if (!$state || !isset($state['steps'])) {
            return;
        }
        
        // Contar TODOS los pasos para mejor UX y consistencia visual
        $totalSteps = 0;
        $completedSteps = 0;
        
        foreach ($state['steps'] as $step) {
            $totalSteps++;
            if ($step['completed'] ?? false) {
                $completedSteps++;
            }
        }
        
        $progress = $totalSteps > 0 ? ($completedSteps / $totalSteps) * 100 : 0;
        $progressBar = str_repeat('█', (int)($progress / 10)) . str_repeat('░', 10 - (int)($progress / 10));
        
        $output->writeln('<fg=cyan>📈 PROGRESO:</> [' . $progressBar . '] ' . round($progress) . '% (' . $completedSteps . '/' . $totalSteps . ')');
    }
    
    private function getMainOptionForStep(array $step): ?array
    {
        // Use step title instead of hardcoded ID mapping
        $projectPath = getcwd();
        $title = strtolower($step['title']);
        
        if (strpos($title, 'profile') !== false) {
            return ['key' => '3', 'label' => '📋 Profile  - Aplicar profile'];
        }
        
        if (strpos($title, 'credenciales') !== false || strpos($title, 'auth') !== false) {
            return ['key' => 'T', 'label' => '🔐 Auth     - Configurar credenciales'];
        }
        
        if (strpos($title, 'docker') !== false) {
            return ['key' => '4', 'label' => '🐳 Docker   - Levantar contenedores + DB'];
        }
        
        if (strpos($title, 'wordpress') !== false || strpos($title, 'instalar') !== false) {
            return ['key' => '2', 'label' => '⚙️  Setup    - Instalar WordPress'];
        }
        
        if (strpos($title, 'tema') !== false || strpos($title, 'theme') !== false) {
            return ['key' => 'T', 'label' => '🎨 Themes   - Activar tema'];
        }
        
        if (strpos($title, 'plugin') !== false) {
            return ['key' => 'P', 'label' => '🔌 Plugins  - Activar plugins'];
        }
        
        if (strpos($title, 'acorn') !== false) {
            return ['key' => 'A', 'label' => '🌱 Acorn    - Configurar Acorn'];
        }
        
        if (strpos($title, 'seed') !== false) {
            return ['key' => 'S', 'label' => '🌱 Seed     - Ejecutar seeders'];
        }
        
        return null;
    }
    
    private function showFullMenu(InputInterface $input, OutputInterface $output, $helper, bool $needsAuth): int
    {
        // Redirigir al menú completo original (sin --guia, con skip-validation)
        $output->writeln('<info>Cambiando a menú completo...</info>');
        $output->writeln('');
        
        // Crear nueva instancia sin --guia pero CON --skip-validation (ya se evaluó)
        $newInput = clone $input;
        $newInput->setOption('guia', false);
        $newInput->setOption('skip-validation', true); // CLAVE: Evitar re-evaluación
        
        return $this->execute($newInput, $output);
    }
    
    private function showInconsistencies(OutputInterface $output): void
    {
        $inconsistencies = $this->stateService->detectInconsistencies(getcwd());
        
        if (!empty($inconsistencies)) {
            $output->writeln('<fg=yellow;options=bold>⚠️  Inconsistencias Detectadas (' . count($inconsistencies) . '):</>');
            $output->writeln('');
            
            foreach ($inconsistencies as $inconsistency) {
                $output->writeln('  <fg=yellow>⚠</> ' . $inconsistency['message']);
            }
            $output->writeln('');
        }
    }
    
    private function hasInconsistencies(): bool
    {
        $inconsistencies = $this->stateService->detectInconsistencies(getcwd());
        return !empty($inconsistencies);
    }
    
    private function fixInconsistencies(OutputInterface $output): void
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>🔧 Reparando inconsistencias...</>');
        
        $inconsistencies = $this->stateService->detectInconsistencies(getcwd());
        
        if (empty($inconsistencies)) {
            $output->writeln('<info>✓ No hay inconsistencias que reparar</info>');
            return;
        }
        
        $envPath = getcwd() . '/.env';
        $envContent = file_get_contents($envPath);
        $envLines = explode("\n", $envContent);
        $modified = false;
        
        foreach ($inconsistencies as $inconsistency) {
            $output->writeln('<comment>Procesando: ' . $inconsistency['type'] . '</comment>');
            
            switch ($inconsistency['type']) {
                case 'wp_port_missing':
                    // Agregar WP_PORT y actualizar WP_HOME
                    $wpPort = $inconsistency['docker_value'];
                    $envLines = $this->addOrUpdateEnvLine($envLines, 'WP_PORT', $wpPort);
                    $envLines = $this->addOrUpdateEnvLine($envLines, 'WP_HOME', '"http://localhost:${WP_PORT}"');
                    $modified = true;
                    $output->writeln('<info>✓ WP_PORT agregado: ' . $wpPort . '</info>');
                    break;
                    
                case 'mysql_port_missing':
                    // Agregar DB_PORT
                    $dbPort = $inconsistency['docker_value'];
                    $envLines = $this->addOrUpdateEnvLine($envLines, 'DB_PORT', $dbPort);
                    $modified = true;
                    $output->writeln('<info>✓ DB_PORT agregado: ' . $dbPort . '</info>');
                    break;
                    
                case 'redis_port_mismatch':
                    // Actualizar REDIS_PORT
                    $redisPort = $inconsistency['docker_value'];
                    $output->writeln('<comment>Actualizando REDIS_PORT de ' . $inconsistency['env_value'] . ' a ' . $redisPort . '</comment>');
                    $envLines = $this->addOrUpdateEnvLine($envLines, 'REDIS_PORT', $redisPort);
                    $modified = true;
                    $output->writeln('<info>✓ REDIS_PORT actualizado: ' . $redisPort . '</info>');
                    break;
                    
                default:
                    $output->writeln('<comment>Tipo no manejado: ' . $inconsistency['type'] . '</comment>');
                    break;
            }
        }
        
        if ($modified) {
            file_put_contents($envPath, implode("\n", $envLines));
            $output->writeln('');
            $output->writeln('<info>✅ Archivo .env actualizado exitosamente</info>');
            $output->writeln('<comment>Reinicia los contenedores Docker para aplicar los cambios:</comment>');
            $output->writeln('<comment>  docker-compose down && docker-compose up -d</comment>');
        } else {
            $output->writeln('<comment>No se pudieron reparar automáticamente todas las inconsistencias</comment>');
        }
        
        $output->writeln('');
        $output->writeln('<comment>Presiona Enter para continuar...</comment>');
        fgets(STDIN);
    }
    
    private function addOrUpdateEnvLine(array $envLines, string $key, $value): array
    {
        $found = false;
        
        // Buscar línea existente (más robusto)
        foreach ($envLines as $index => $line) {
            $trimmedLine = trim($line);
            if (strpos($trimmedLine, $key . '=') === 0) {
                $envLines[$index] = $key . '=' . $value;
                $found = true;
                break;
            }
        }
        
        // Si no existe, agregar en posición apropiada
        if (!$found) {
            $insertIndex = count($envLines);
            
            // Buscar mejor posición para insertar
            foreach ($envLines as $index => $line) {
                $trimmedLine = trim($line);
                if ($key === 'WP_PORT' && strpos($trimmedLine, 'WP_ENV=') === 0) {
                    $insertIndex = $index + 1;
                    break;
                } elseif ($key === 'DB_PORT' && strpos($trimmedLine, 'DB_HOST=') === 0) {
                    $insertIndex = $index + 1;
                    break;
                } elseif ($key === 'REDIS_PORT' && (strpos($trimmedLine, 'REDIS_HOST=') === 0 || strpos($trimmedLine, 'REDIS_URL=') === 0)) {
                    $insertIndex = $index + 1;
                    break;
                }
            }
            
            array_splice($envLines, $insertIndex, 0, $key . '=' . $value);
        }
        
        return $envLines;
    }
}
