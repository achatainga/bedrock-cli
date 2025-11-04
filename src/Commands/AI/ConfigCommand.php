<?php

namespace Roots\BedrockCli\Commands\AI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Roots\BedrockCli\Services\AIService;

class ConfigCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai:config')
            ->setDescription('Configurar API keys para IA');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  Configuración de IA - Bedrock   </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $providerQuestion = new ChoiceQuestion(
            'Selecciona el proveedor de IA:',
            ['openrouter', 'gemini'],
            0
        );
        $provider = $helper->ask($input, $output, $providerQuestion);

        $output->writeln('');

        if ($provider === 'openrouter') {
            $output->writeln('<info>OpenRouter - Modelos FREE disponibles:</info>');
            $output->writeln('  • deepseek-chat-v3.1:free (671B params, reasoning)');
            $output->writeln('  • qwen3-coder:free (480B params, coding)');
            $output->writeln('');
            $output->writeln('<fg=cyan>Obtén tu API key en:</> https://openrouter.ai/keys');
        } else {
            $output->writeln('<info>Google Gemini - Modelo: gemini-1.5-flash</info>');
            $output->writeln('  • Gratis hasta 15 RPM');
            $output->writeln('');
            $output->writeln('<fg=cyan>Obtén tu API key en:</> https://aistudio.google.com/app/apikey');
        }

        $output->writeln('');

        $apiKeyQuestion = new Question('Ingresa tu API key: ');
        $apiKeyQuestion->setHidden(true);
        $apiKey = $helper->ask($input, $output, $apiKeyQuestion);

        if (empty($apiKey)) {
            $output->writeln('<error>API key no puede estar vacía</error>');
            return Command::FAILURE;
        }

        $model = $this->selectModel($input, $output, $helper, $provider);

        $output->writeln('');
        $output->writeln('<comment>Validando API key...</comment>');

        $config = [
            'provider' => $provider,
            'api_key' => $apiKey,
            'model' => $model,
            'max_tokens' => 2000,
            'temperature' => 0.7
        ];

        $aiService = new AIService($config);

        try {
            $response = $aiService->ask('Responde solo: OK');
            
            if (stripos($response, 'ok') !== false) {
                $output->writeln('<info>✓ API key válida</info>');
            } else {
                $output->writeln('<comment>⚠ API key funciona pero respuesta inesperada</comment>');
            }
        } catch (\Exception $e) {
            $output->writeln('<error>✗ Error al validar API key: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $aiService->saveConfig($config);

        $output->writeln('');
        $output->writeln('<fg=green;options=bold>✓ Configuración guardada exitosamente</>');
        $output->writeln('');
        $output->writeln('<fg=cyan>Próximos pasos:</>');
        $output->writeln('  • Usa: <fg=white>bedrock ai:ask "tu pregunta"</>');
        $output->writeln('  • Usa: <fg=white>bedrock ai:chat</> para conversación');
        $output->writeln('  • Usa: <fg=white>bedrock ai:diagnose</> para diagnóstico');
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function selectModel(InputInterface $input, OutputInterface $output, $helper, string $provider): string
    {
        if ($provider === 'openrouter') {
            $modelQuestion = new ChoiceQuestion(
                'Selecciona el modelo:',
                [
                    'deepseek/deepseek-chat-v3.1:free',
                    'qwen/qwen3-coder:free'
                ],
                0
            );
            return $helper->ask($input, $output, $modelQuestion);
        }

        return 'gemini-1.5-flash';
    }
}
