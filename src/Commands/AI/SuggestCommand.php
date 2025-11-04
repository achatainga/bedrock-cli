<?php

namespace Roots\BedrockCli\Commands\AI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\AIService;
use Roots\BedrockCli\Services\AIContextBuilder;

class SuggestCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai:suggest')
            ->setDescription('Sugerencias inteligentes para el proyecto');
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
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Sugerencias IA - Bedrock CLI    </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $output->writeln('<comment>💡 Analizando proyecto para sugerencias...</comment>');
        $output->writeln('');

        $contextBuilder = new AIContextBuilder();
        $context = $contextBuilder->buildContext();

        try {
            $prompt = "Basándote en el estado actual del proyecto, proporciona:\n";
            $prompt .= "1. Sugerencias de seguridad\n";
            $prompt .= "2. Mejoras de performance\n";
            $prompt .= "3. Recomendaciones de desarrollo\n";
            $prompt .= "4. Próximos pasos sugeridos\n\n";
            $prompt .= "Sé específico y práctico. Incluye comandos cuando sea relevante.";

            $response = $aiService->ask($prompt, $context);

            $output->writeln('<fg=cyan>💡 SUGERENCIAS PARA TU PROYECTO</>');
            $output->writeln('');
            $output->writeln($response);
            $output->writeln('');
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
