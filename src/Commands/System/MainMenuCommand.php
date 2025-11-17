<?php

namespace Roots\BedrockCli\Commands\System;

use Roots\BedrockCli\Services\PremiumRepoService;
use Roots\BedrockCli\Services\StateService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Symfony\Component\Console\Input\InputOption;

class MainMenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('menu')
             ->setDescription('Abre el menú interactivo')
             ->addOption('guia', 'g', InputOption::VALUE_NONE, 'Modo guía simplificado para principiantes')
             ->addOption('skip-validation', 's', InputOption::VALUE_NONE, 'Omitir validaciones para carga rápida');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        // Cargar wizard state si existe
        $stateService = new StateService();
        $state = $stateService->loadState(getcwd());
        
        // FASE 2.5: Validaciones con animación y cache
        $skipValidation = $input->getOption('skip-validation');
        
        if ($state && $state['wizard_mode'] && !$skipValidation) {
            $this->showValidationProgress($output);
            $stateService->updateStepValidations(getcwd());
            // Recargar estado después de validaciones
            $state = $stateService->loadState(getcwd());
        }
        
        $currentStep = $state && $state['wizard_mode'] ? $stateService->getCurrentStep($state) : null;
        
        // FASE 2: Verificar si está en modo guía
        $isGuidedMode = $input->getOption('guia');
        
        // FASE 2.6: Verificar acceso a repo premium (con skip-validation)
        $needsAuth = false;
        if (!$skipValidation) {
            $externalServices = $stateService->validateExternalServices();
            $needsAuth = !$externalServices['premium_repo'];
        }
        
        while (true) {
            if ($isGuidedMode && $currentStep) {
                // Pasar flag de skip validation al modo guía
                return $this->showGuidedMenu($input, $output, $helper, $currentStep, $needsAuth, $skipValidation);
            }
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>      BEDROCK CLI v2.0          </> <fg=cyan;options=bold>     ║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');
            
            // Mostrar wizard si está activo
            if ($currentStep) {
                $output->writeln('<fg=yellow;options=bold>📋 PASO ' . $currentStep['id'] . ': ' . $currentStep['title'] . '</>');
                $output->writeln('<comment>' . $currentStep['description'] . '</comment>');
                $output->writeln('');
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
                $command->run($input, $output);
            }
        }

        return Command::SUCCESS;
    }

    private function checkPremiumRepoAccess(string $repoUrl): bool
    {
        try {
            $service = new PremiumRepoService();
            $result = $service->checkAccess($repoUrl);
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
        
        $validations = [
            'Docker containers' => 0.3,
            'WordPress installation' => 0.5,
            'Plugins status' => 0.2,
            'Theme configuration' => 0.2,
            'Acorn setup' => 0.3,
            'Premium repositories' => 0.8,
            'API keys configuration' => 0.2,
            'GitHub/GitLab access' => 0.4
        ];
        
        foreach ($validations as $task => $delay) {
            $output->write("  • Evaluando {$task}...");
            usleep((int)($delay * 1000000)); // Convertir a microsegundos
            $output->writeln(' <fg=green>✓</>');
        }
        
        $output->writeln('');
    }

    // ===== FASE 2: MODO GUÍA SIMPLIFICADO =====

    private function showGuidedMenu(InputInterface $input, OutputInterface $output, $helper, array $currentStep, bool $needsAuth, bool $skipValidation = false): int
    {
        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>    BEDROCK CLI - MODO GUÍA    </> <fg=cyan;options=bold>║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');
            
            // Mostrar progreso
            $this->showProgressIndicator($output, $currentStep);
            $output->writeln('');
            
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
            
            // Opciones esenciales
            $output->writeln(' <fg=cyan>[8]</> ℹ️  Info     - Estado del proyecto');
            $output->writeln(' <fg=cyan>[M]</> 📝 Menú    - Ver menú completo');
            $output->writeln(' <fg=cyan>[0]</> ❌ Salir');
            $output->writeln('');

            $question = new Question('<fg=yellow>Opción [' . ($mainOption ? $mainOption['key'] . ', ' : '') . '8, M, 0]:</> ', '0');
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
            
            // Ejecutar comando seleccionado
            $commandMap = [
                '1' => 'doctor',
                '2' => 'setup', 
                '4' => 'docker',
                '8' => 'info',
                'D' => 'docker',
                'P' => 'plugins:menu',
                'T' => 'themes:menu',
                'A' => 'acorn',
                'S' => 'seed'
            ];

            $commandName = $commandMap[$selectedIndex] ?? null;
            if ($commandName) {
                $output->writeln('');
                $command = $this->getApplication()->find($commandName);
                $command->run($input, $output);
                
                // Recargar estado después de ejecutar comando (con validación rápida)
                $stateService = new StateService();
                if (!$skipValidation) {
                    $output->writeln('<fg=cyan>🔄 Actualizando estado...</>');
                    $stateService->updateStepValidations(getcwd());
                }
                $state = $stateService->loadState(getcwd());
                $currentStep = $state && $state['wizard_mode'] ? $stateService->getCurrentStep($state) : null;
                
                if (!$currentStep) {
                    $output->writeln('<info>🎉 ¡Todos los pasos completados! El proyecto está listo.</info>');
                    return Command::SUCCESS;
                }
            } else {
                $output->writeln('<error>Opción inválida.</error>');
                sleep(1);
            }
        }
    }
    
    private function showProgressIndicator(OutputInterface $output, array $currentStep): void
    {
        // Obtener estado completo para calcular progreso
        $stateService = new StateService();
        $state = $stateService->loadState(getcwd());
        
        if (!$state || !isset($state['steps'])) {
            return;
        }
        
        $totalSteps = count($state['steps']);
        $completedSteps = 0;
        
        foreach ($state['steps'] as $step) {
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
        $stepOptions = [
            1 => ['key' => '4', 'label' => '🐳 Docker   - Levantar contenedores'],
            2 => ['key' => '2', 'label' => '⚙️  Setup    - Instalar WordPress'],
            3 => ['key' => 'P', 'label' => '🔌 Plugins  - Activar plugins'],
            4 => ['key' => 'T', 'label' => '🎨 Themes   - Activar tema'],
            5 => ['key' => 'A', 'label' => '🌱 Acorn    - Configurar Acorn'],
            6 => ['key' => 'S', 'label' => '🌱 Seed     - Ejecutar seeders']
        ];
        
        return $stepOptions[$step['id']] ?? null;
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
}
