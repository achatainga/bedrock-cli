<?php

namespace Roots\BedrockCli\Commands\AI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\AIService;
use Roots\BedrockCli\Services\AIContextBuilder;
use Roots\BedrockCli\Services\StateDetectorService;

class DiagnoseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai:diagnose')
            ->setDescription('Diagnóstico automático del proyecto con IA');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $aiService = new AIService();

        if (!$aiService->isConfigured()) {
            $output->writeln('<error>IA no configurada. Ejecuta: bedrock ai:config</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Diagnóstico IA - Bedrock CLI    </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $output->writeln('<comment>🔍 Analizando proyecto...</comment>');
        $output->writeln('');

        $stateDetector = new StateDetectorService();
        $state = $stateDetector->detectProjectState();
        $contextBuilder = new AIContextBuilder();
        $context = $contextBuilder->buildContext();

        $output->writeln('<fg=cyan>Estado Actual:</>');;
        $output->writeln($this->formatStatus($state['docker_running'], 'Docker corriendo'));
        $output->writeln($this->formatStatus($state['containers_running'], 'Contenedores activos'));
        $output->writeln($this->formatStatus($state['wp_installed'], 'WordPress instalado'));
        
        if ($state['acorn_installed']) {
            $output->writeln($this->formatStatus($state['acorn_configured'], 'Acorn configurado'));
        }
        
        $output->writeln('');

        if (!empty($state['pending_tasks'])) {
            $output->writeln('<fg=yellow>⚠️  Tareas pendientes detectadas:</>');
            foreach ($state['pending_tasks'] as $task) {
                $output->writeln("  • {$task['name']}");
            }
            $output->writeln('');
        }

        $output->writeln('<comment>🤖 Consultando IA para análisis detallado...</comment>');
        $output->writeln('');

        try {
            $prompt = "Analiza este proyecto Bedrock y proporciona:\n";
            $prompt .= "1. Diagnóstico del estado actual\n";
            $prompt .= "2. Problemas detectados\n";
            $prompt .= "3. Sugerencias de mejora\n";
            $prompt .= "4. Comandos recomendados\n\n";
            $prompt .= "Estado: " . json_encode($state, JSON_PRETTY_PRINT);

            $response = $aiService->ask($prompt, $context);

            $output->writeln('<fg=cyan>📋 Análisis de IA:</>');
            $output->writeln($response);
            $output->writeln('');
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function formatStatus(bool $status, string $message): string
    {
        $icon = $status ? '<info>✓</info>' : '<error>✗</error>';
        return " {$icon} {$message}";
    }
}
