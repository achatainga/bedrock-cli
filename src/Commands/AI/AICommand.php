<?php

namespace Roots\BedrockCli\Commands\AI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Roots\BedrockCli\Services\AIService;

class AICommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai')
            ->setDescription('Menú principal de IA Copilot');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $aiService = new AIService();

        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>    🤖 BEDROCK AI COPILOT          </> <fg=cyan;options=bold>║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');

            if (!$aiService->isConfigured()) {
                $output->writeln('<fg=yellow>⚠️  IA no configurada</>');
                $output->writeln('');
            }

            $options = [
                '1' => '💬 Chat - Conversación interactiva',
                '2' => '❓ Ask - Pregunta rápida',
                '3' => '🔍 Diagnose - Diagnosticar proyecto',
                '4' => '💡 Suggest - Sugerencias contextuales',
                '5' => '⚙️  Config - Configurar API keys',
                '6' => '🎯 Model - Seleccionar modelo',
                '0' => '❌ Salir'
            ];

            foreach ($options as $key => $label) {
                $output->writeln("  <fg=cyan>[{$key}]</> {$label}");
            }

            $output->writeln('');

            $question = new Question('Selecciona una opción: ');
            $choice = $helper->ask($input, $output, $question);

            $output->writeln('');

            switch ($choice) {
                case '1':
                    $this->getApplication()->find('ai:chat')->run($input, $output);
                    break;
                case '2':
                    $questionText = new Question('Pregunta: ');
                    $userQuestion = $helper->ask($input, $output, $questionText);
                    if ($userQuestion) {
                        $newInput = clone $input;
                        $newInput->setArgument('question', $userQuestion);
                        $this->getApplication()->find('ai:ask')->run($newInput, $output);
                    }
                    break;
                case '3':
                    $this->getApplication()->find('ai:diagnose')->run($input, $output);
                    break;
                case '4':
                    $this->getApplication()->find('ai:suggest')->run($input, $output);
                    break;
                case '5':
                    $this->getApplication()->find('ai:config')->run($input, $output);
                    break;
                case '6':
                    $this->getApplication()->find('ai:model')->run($input, $output);
                    break;
                case '0':
                    return Command::SUCCESS;
                default:
                    $output->writeln('<error>Opción inválida</error>');
            }
        }

        return Command::SUCCESS;
    }
}
