<?php

namespace Roots\BedrockCli\Commands\System;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\ProjectValidationService;
use Symfony\Component\Process\Process;

class InfoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('info')
            ->setDescription('Mostrar información del proyecto y estado actual');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<fg=cyan>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan>║</>   ℹ️  INFO - Estado del Proyecto     <fg=cyan>║</>');
        $output->writeln('<fg=cyan>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln('<comment>Muestra información completa del proyecto, Docker, WordPress y tareas pendientes.</comment>');
        $output->writeln('');
        
        // Detectar estado usando unified validation service
        $validationService = new ProjectValidationService();
        $projectPath = getcwd();
        
        $dockerValidation = $validationService->validateDocker($projectPath);
        $dbValidation = $validationService->validateDatabase($projectPath);
        $wpValidation = $validationService->validateWordPress($projectPath);
        $acornValidation = $validationService->validateAcorn($projectPath);
        $inconsistencies = $validationService->detectInconsistencies($projectPath);
        
        // Build state array for compatibility
        $state = [
            'is_bedrock' => file_exists($projectPath . '/composer.json') && file_exists($projectPath . '/config/application.php'),
            'env_exists' => file_exists($projectPath . '/.env'),
            'docker_installed' => $dockerValidation->isValid || $this->isDockerInstalled(),
            'docker_running' => $dockerValidation->isValid,
            'containers_running' => $dockerValidation->isValid,
            'wp_installed' => $wpValidation->isValid,
            'acorn_installed' => $acornValidation->isPackageInstalled,
            'acorn_configured' => $acornValidation->isValid,
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
            ]]
        ];
        
        // Información del proyecto
        $this->showProjectInfo($output);
        
        // Estado del sistema
        $output->writeln('<fg=cyan;options=bold>Estado del Sistema:</>')
;
        $output->writeln($this->formatStatus($state['docker_installed'], 'Docker Desktop instalado'));
        $output->writeln($this->formatStatus($state['docker_running'], 'Docker corriendo'));
        $output->writeln($this->formatStatus($state['containers_running'], 'Contenedores activos'));
        $output->writeln('');
        
        // Estado de WordPress
        $output->writeln('<fg=cyan;options=bold>Estado de WordPress:</>')
;
        $output->writeln($this->formatStatus($state['is_bedrock'], 'Proyecto Bedrock'));
        $output->writeln($this->formatStatus($state['env_exists'], 'Archivo .env configurado'));
        $output->writeln($this->formatStatus($state['wp_installed'], 'WordPress instalado'));
        $output->writeln('');
        
        // Estado de Acorn (granular)
        if ($state['acorn_installed']) {
            $output->writeln('<fg=cyan;options=bold>Estado de Acorn:</>');
            $output->writeln($this->formatStatus($acornValidation->isPackageInstalled, 'Paquete Composer'));
            $output->writeln($this->formatStatus($acornValidation->isStorageInitialized, 'Storage inicializado'));
            $output->writeln($this->formatStatus($acornValidation->areConfigsPublished, 'Configs publicados'));
            
            if ($acornValidation->isValid) {
                $output->writeln('  <info>✓ Estado general: Fully configured</info>');
            } else {
                $output->writeln('  <comment>⚠ Estado general: ' . $acornValidation->message . '</comment>');
            }
            $output->writeln('');
        }
        
        // Estado de temas y plugins
        if ($state['wp_installed']) {
            $this->showThemesStatus($output);
            $this->showPluginsStatus($output);
        }
        
        // Inconsistencias
        if (!empty($state['inconsistencies'])) {
            $this->showInconsistencies($output, $state['inconsistencies']);
        }
        
        // Tareas pendientes (usando detección automática)
        if (!empty($state['pending_tasks'])) {
            $this->showPendingTasksAuto($output, $state['pending_tasks']);
        } else {
            $output->writeln('<fg=green>✓ No hay tareas pendientes</>');
            $output->writeln('');
        }
        
        // Próximos pasos sugeridos
        $this->showSuggestedActions($output, $state);
        
        return Command::SUCCESS;
    }
    
    private function showProjectInfo(OutputInterface $output): void
    {
        $composerFile = getcwd() . '/composer.json';
        
        if (!file_exists($composerFile)) {
            return;
        }
        
        $composer = json_decode(file_get_contents($composerFile), true);
        
        $output->writeln('<fg=cyan;options=bold>Información del Proyecto:</>')
;
        $output->writeln('  Nombre: ' . ($composer['name'] ?? 'N/A'));
        $output->writeln('  Tipo: ' . ($composer['type'] ?? 'project'));
        $output->writeln('  Ruta: ' . getcwd());
        $output->writeln('');
    }
    
    private function formatStatus(bool $status, string $message): string
    {
        $icon = $status ? '<info>✓</info>' : '<error>✗</error>';
        return "  {$icon} {$message}";
    }
    
    private function showInconsistencies(OutputInterface $output, array $inconsistencies): void
    {
        $output->writeln('<fg=yellow;options=bold>⚠️  Inconsistencias Detectadas (' . count($inconsistencies) . '):</>');
        $output->writeln('');
        
        foreach ($inconsistencies as $issue) {
            $severity = $issue['severity'] ?? 'warning';
            $icon = $severity === 'error' ? '<error>✗</error>' : '<comment>⚠</comment>';
            $output->writeln("  {$icon} {$issue['message']}");
            
            if (isset($issue['files'])) {
                $output->writeln("     Archivos: " . implode(', ', $issue['files']));
            }
        }
        
        $output->writeln('');
    }
    
    private function showPendingTasksAuto(OutputInterface $output, array $tasks): void
    {
        $output->writeln('<fg=cyan;options=bold>📝 Tareas Pendientes (' . count($tasks) . '):</>');
        $output->writeln('');
        
        foreach ($tasks as $task) {
            $icon = match($task['severity']) {
                'critical' => '<error>❗</error>',
                'high' => '<comment>⚠</comment>',
                'medium' => '<info>ℹ</info>',
                default => '<info>•</info>',
            };
            
            $output->writeln("  {$icon} {$task['name']}");
            $output->writeln("     <comment>Comando:</comment> <fg=white>{$task['command']}</>");
        }
        
        $output->writeln('');
    }
    
    private function showPendingTasksOld(OutputInterface $output, array $state): void
    {
        $tasks = [];
        
        if (!$state['docker_running']) {
            $tasks[] = ['priority' => 'alta', 'task' => 'Docker no está corriendo', 'action' => 'bedrock docker --up'];
        }
        
        if (!$state['wp_installed']) {
            $tasks[] = ['priority' => 'alta', 'task' => 'WordPress no está instalado', 'action' => 'bedrock setup'];
        }
        
        if ($state['acorn_installed'] && !$state['acorn_configured']) {
            $tasks[] = ['priority' => 'media', 'task' => 'Acorn instalado pero no configurado', 'action' => 'bedrock acorn'];
        }
        
        if (empty($tasks)) {
            $output->writeln('<fg=green>✓ No hay tareas pendientes</>');
            $output->writeln('');
            return;
        }
        
        $output->writeln('<fg=yellow;options=bold>Tareas Pendientes (' . count($tasks) . '):</>')
;
        
        foreach ($tasks as $task) {
            $icon = $task['priority'] === 'alta' ? '<fg=red>⚠️</>' : '<fg=yellow>ℹ️</>';
            $output->writeln("  {$icon}  {$task['task']}");
            $output->writeln("      <comment>Solución:</comment> {$task['action']}");
        }
        
        $output->writeln('');
    }
    
    private function showThemesStatus(OutputInterface $output): void
    {
        $themesDir = getcwd() . '/web/app/themes';
        if (!is_dir($themesDir)) {
            return;
        }
        
        $themes = array_filter(scandir($themesDir), function($item) use ($themesDir) {
            return $item !== '.' && $item !== '..' && is_dir($themesDir . '/' . $item);
        });
        
        if (empty($themes)) {
            return;
        }
        
        // Obtener tema activo desde WordPress
        $activeTheme = $this->getActiveTheme($output);
        
        $output->writeln('<fg=cyan;options=bold>Temas:</>');
        
        if ($activeTheme) {
            $output->writeln("  <info>✓</info> Activo: <fg=green;options=bold>{$activeTheme}</>");
        }
        
        $availableThemes = array_filter($themes, fn($t) => $t !== $activeTheme);
        if (!empty($availableThemes)) {
            $output->writeln('  Disponibles: ' . implode(', ', $availableThemes));
        }
        
        $output->writeln('');
    }
    
    private function showPluginsStatus(OutputInterface $output): void
    {
        $pluginsDir = getcwd() . '/web/app/plugins';
        if (!is_dir($pluginsDir)) {
            return;
        }
        
        $plugins = array_filter(scandir($pluginsDir), function($item) use ($pluginsDir) {
            return $item !== '.' && $item !== '..' && is_dir($pluginsDir . '/' . $item);
        });
        
        if (empty($plugins)) {
            return;
        }
        
        // Obtener plugins activos desde WordPress
        $activePlugins = $this->getActivePlugins($output);
        
        $output->writeln('<fg=cyan;options=bold>Plugins:</>');
        
        if (!empty($activePlugins)) {
            $output->writeln("  <info>✓</info> Activos (" . count($activePlugins) . "):");
            foreach ($activePlugins as $idx => $plugin) {
                $output->writeln("    " . ($idx + 1) . ". {$plugin}");
            }
        }
        
        $inactivePlugins = array_filter($plugins, fn($p) => !in_array($p, $activePlugins));
        if (!empty($inactivePlugins)) {
            $output->writeln('  Inactivos: ' . implode(', ', $inactivePlugins));
        }
        
        $output->writeln('');
    }
    
    private function getActiveTheme(?OutputInterface $output = null): ?string
    {
        try {
            $process = new \Symfony\Component\Process\Process(
                ['docker-compose', 'exec', '-T', 'web', 'wp', 'theme', 'list', '--status=active', '--field=name'],
                getcwd()
            );
            $process->run();
            
            if ($process->isSuccessful()) {
                return trim($process->getOutput()) ?: null;
            }

            if ($output && $output->isVerbose()) {
                $err = trim($process->getErrorOutput());
                $output->writeln("<comment>[InfoCommand] Fallo al listar temas activos: {$err}</comment>");
            }
        } catch (\Throwable $e) {
            if ($output && $output->isVerbose()) {
                $output->writeln("<comment>[InfoCommand] Excepción al consultar tema activo: {$e->getMessage()}</comment>");
            }
        }
        
        return null;
    }
    
    private function getActivePlugins(?OutputInterface $output = null): array
    {
        try {
            $process = new \Symfony\Component\Process\Process(
                ['docker-compose', 'exec', '-T', 'web', 'wp', 'plugin', 'list', '--status=active', '--field=name'],
                getcwd()
            );
            $process->run();
            
            if ($process->isSuccessful()) {
                $out = trim($process->getOutput());
                return $out ? array_filter(array_map('trim', explode("\n", $out))) : [];
            }

            if ($output && $output->isVerbose()) {
                $err = trim($process->getErrorOutput());
                $output->writeln("<comment>[InfoCommand] Fallo al listar plugins activos: {$err}</comment>");
            }
        } catch (\Throwable $e) {
            if ($output && $output->isVerbose()) {
                $output->writeln("<comment>[InfoCommand] Excepción al consultar plugins activos: {$e->getMessage()}</comment>");
            }
        }
        
        return [];
    }
    
    private function showSuggestedActions(OutputInterface $output, array $state): void
    {
        $output->writeln('<fg=cyan;options=bold>Próximos Pasos Sugeridos:</>')
;
        
        if (!$state['docker_running']) {
            $output->writeln('  1. Iniciar Docker: <fg=white>bedrock docker --up</>');
        } elseif (!$state['wp_installed']) {
            $output->writeln('  1. Instalar WordPress: <fg=white>bedrock setup</>');
        } elseif ($state['acorn_installed'] && !$state['acorn_configured']) {
            $output->writeln('  1. Configurar Acorn: <fg=white>bedrock acorn</>');
        } else {
            $output->writeln('  1. Activar tema: <fg=white>bedrock themes:activate</>');
            $output->writeln('  2. Activar plugins: <fg=white>bedrock plugins:activate</>');
            $output->writeln('  3. Crear snapshot: <fg=white>bedrock snapshot --create --name=backup</>');
        }
        
        $output->writeln('');
    }

    private function isDockerInstalled(): bool
    {
        $process = new Process(['docker', '--version']);
        $process->setTimeout(5);
        $process->run();
        return $process->isSuccessful();
    }
}
