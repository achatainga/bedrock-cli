<?php

namespace Roots\BedrockCli\Commands\AI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Roots\BedrockCli\Services\AIService;
use Roots\BedrockCli\Services\AIContextBuilder;

class ChatCommand extends Command
{
    private array $history = [];

    protected function configure(): void
    {
        $this
            ->setName('ai:chat')
            ->setDescription('Chat interactivo con IA');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $aiService = new AIService();

        if (!$aiService->isConfigured()) {
            $output->writeln('<error>IA no configurada. Ejecuta: bedrock ai:config</error>');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $contextBuilder = new AIContextBuilder();

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>    Chat IA - Bedrock CLI          </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');
        $output->writeln('<info>Comandos especiales:</info>');
        $output->writeln('  /exit   - Salir del chat');
        $output->writeln('  /clear  - Limpiar historial');
        $output->writeln('  /context - Mostrar contexto');
        $output->writeln('');
        $output->writeln('<fg=cyan>🤖 IA:</> ¿En qué puedo ayudarte?');
        $output->writeln('');

        while (true) {
            $question = new Question('<fg=green>👤 Tú:</> ');
            $userInput = $helper->ask($input, $output, $question);

            if (empty($userInput)) {
                continue;
            }

            if ($userInput === '/exit') {
                $output->writeln('<comment>Hasta luego!</comment>');
                break;
            }

            if ($userInput === '/clear') {
                $this->history = [];
                $output->writeln('<info>Historial limpiado</info>');
                $output->writeln('');
                continue;
            }

            if ($userInput === '/context') {
                $context = $contextBuilder->buildContext();
                $output->writeln('<fg=cyan>Contexto actual:</>');
                $output->writeln(json_encode($context, JSON_PRETTY_PRINT));
                $output->writeln('');
                continue;
            }

            try {
                $result = $aiService->chat($userInput, $this->history);
                $this->history = $result['history'];

                $output->writeln('');
                $output->writeln('<fg=cyan>🤖 IA:</> ' . $result['response']);
                $output->writeln('');
            } catch (\Exception $e) {
                $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
                $output->writeln('');
            }
        }

        return Command::SUCCESS;
    }
}
