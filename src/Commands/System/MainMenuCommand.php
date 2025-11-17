<?php

namespace Roots\BedrockCli\Commands\System;

use Roots\BedrockCli\Services\PremiumRepoService;
use Roots\BedrockCli\Services\StateService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;

class MainMenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('menu')
             ->setDescription('Abre el menú interactivo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        // Cargar wizard state si existe
        $stateService = new StateService();
        $state = $stateService->loadState(getcwd());
        
        // FASE 1: Actualizar validaciones reales antes de mostrar menú
        if ($state && $state['wizard_mode']) {
            $stateService->updateStepValidations(getcwd());
            // Recargar estado después de validaciones
            $state = $stateService->loadState(getcwd());
        }
        
        $currentStep = $state && $state['wizard_mode'] ? $stateService->getCurrentStep($state) : null;
        
        // Verificar acceso a repo premium
        $repoUrl = getenv('PREMIUM_REPO_URL') ?: 'https://gitlab.com/detodo24/detodo24-premium-assets.git';
        $needsAuth = $this->checkPremiumRepoAccess($repoUrl);
        
        while (true) {
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
}
