<?php

namespace Roots\BedrockCli\Commands\AI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Roots\BedrockCli\Services\AIService;

class AskCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai:ask')
            ->setDescription('Pregunta rápida a la IA')
            ->addArgument('question', InputArgument::REQUIRED, 'Pregunta para la IA');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $aiService = new AIService();

        if (!$aiService->isConfigured()) {
            $output->writeln('<error>IA no configurada. Ejecuta: bedrock ai:config</error>');
            return Command::FAILURE;
        }

        $question = $input->getArgument('question');

        $output->writeln('');
        $output->writeln('<comment>🤖 Consultando IA...</comment>');
        $output->writeln('');

        try {
            $response = $aiService->ask($question);
            
            $output->writeln('<fg=cyan>Respuesta:</>');
            $output->writeln($response);
            $output->writeln('');
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
