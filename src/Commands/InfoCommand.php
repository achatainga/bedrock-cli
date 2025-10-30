<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\StateDetectorService;

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
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Información del Proyecto Bedrock  </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        
        // Detectar estado
        $stateDetector = new StateDetectorService();
        $state = $stateDetector->detectProjectState();
        
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
        
        // Estado de Acorn
        if ($state['acorn_installed']) {
            $output->writeln('<fg=cyan;options=bold>Estado de Acorn:</>')
;
            $output->writeln($this->formatStatus(true, 'Paquete instalado'));
            $output->writeln($this->formatStatus($state['acorn_configured'], 'Storage y configs configurados'));
            $output->writeln('');
        }
        
        // Tareas pendientes
        $this->showPendingTasks($output, $state);
        
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
    
    private function showPendingTasks(OutputInterface $output, array $state): void
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
            $output->writeln('  1. Ver plugins: <fg=white>bedrock plugins</>');
            $output->writeln('  2. Ver temas: <fg=white>bedrock themes</>');
            $output->writeln('  3. Crear snapshot: <fg=white>bedrock snapshot --create --name=backup</>');
        }
        
        $output->writeln('');
    }
}
