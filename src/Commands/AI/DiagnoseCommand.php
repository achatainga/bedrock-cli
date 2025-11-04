<?php

namespace Roots\BedrockCli\Commands\AI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\AIService;
use Roots\BedrockCli\Services\AIContextBuilder;
use Roots\BedrockCli\Services\ProjectDiagnosticService;

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

        // Verificar si estamos en un proyecto Bedrock
        if (!file_exists(getcwd() . '/composer.json')) {
            $output->writeln('<error>⚠️  No estás en un proyecto Bedrock</error>');
            $output->writeln('<comment>Directorio actual: ' . getcwd() . '</comment>');
            $output->writeln('');
            $output->writeln('<info>Navega a tu proyecto primero:</info>');
            $output->writeln('  cd C:\\code\\detodo24-bedrock');
            $output->writeln('  bedrock ai:diagnose');
            return Command::FAILURE;
        }

        $output->writeln('<comment>🔍 Analizando proyecto...</comment>');
        $output->writeln('');

        $diagnostic = new ProjectDiagnosticService();
        $report = $diagnostic->generateDiagnosticReport();
        $contextBuilder = new AIContextBuilder();
        $context = $contextBuilder->buildContext();

        $output->writeln('<fg=cyan>Estado Actual:</>');
        $output->writeln($this->formatStatus($report['docker']['running'], 'Docker corriendo'));
        $output->writeln($this->formatStatus(!empty($report['docker']['containers']), 'Contenedores activos'));
        $output->writeln($this->formatStatus($report['wordpress']['installed'], 'WordPress instalado'));
        
        if ($report['wordpress']['installed']) {
            $output->writeln("  <fg=green>→</> WP {$report['wordpress']['version']}");
            $output->writeln("  <fg=green>→</> " . count($report['plugins']['active']) . " plugins activos");
            $output->writeln("  <fg=green>→</> Tema: " . ($report['themes']['active']['name'] ?? 'N/A'));
        }
        
        $output->writeln('');

        $output->writeln('<comment>🤖 Consultando IA para análisis detallado...</comment>');
        $output->writeln('');

        try {
            $prompt = "Analiza este proyecto Bedrock y proporciona:\n";
            $prompt .= "1. Diagnóstico del estado actual\n";
            $prompt .= "2. Problemas detectados (si los hay)\n";
            $prompt .= "3. Sugerencias de mejora\n";
            $prompt .= "4. Comandos específicos para ESTE proyecto\n\n";
            $prompt .= "IMPORTANTE: Este es el proyecto en " . getcwd() . "\n\n";
            $prompt .= "Reporte completo: " . json_encode($report, JSON_PRETTY_PRINT);

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
