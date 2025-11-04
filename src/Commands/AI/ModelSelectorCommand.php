<?php

namespace Roots\BedrockCli\Commands\AI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Roots\BedrockCli\Services\AIService;

class ModelSelectorCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('ai:model')
            ->setDescription('Seleccionar modelo de IA');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $aiService = new AIService();
        $config = $aiService->isConfigured() ? $this->loadConfig() : [];

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
        $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>    Selector de Modelos IA        </> <fg=cyan;options=bold>║</>');
        $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
        $output->writeln('');

        $providerQuestion = new ChoiceQuestion(
            'Proveedor:',
            ['OpenRouter', 'Gemini'],
            $config['provider'] === 'gemini' ? 1 : 0
        );
        $provider = $helper->ask($input, $output, $providerQuestion);

        $output->writeln('');

        if ($provider === 'OpenRouter') {
            $models = [
                'deepseek/deepseek-chat-v3.1:free' => 'DeepSeek Chat v3.1 (FREE)',
                'qwen/qwen3-coder:free' => 'Qwen3 Coder (FREE)',
                'meta-llama/llama-3.2-3b-instruct:free' => 'Llama 3.2 3B (FREE)',
                'google/gemma-2-9b-it:free' => 'Gemma 2 9B (FREE)',
            ];
        } else {
            $models = [
                'gemini-2.5-flash' => 'Gemini 2.5 Flash (Estable)',
                'gemini-2.5-pro' => 'Gemini 2.5 Pro (Estable)',
                'gemini-flash-latest' => 'Gemini Flash Latest (Auto-update)',
                'gemini-pro-latest' => 'Gemini Pro Latest (Auto-update)',
                'gemini-2.0-flash' => 'Gemini 2.0 Flash',
            ];
        }

        $modelQuestion = new ChoiceQuestion(
            'Modelo:',
            array_values($models),
            0
        );
        $selectedLabel = $helper->ask($input, $output, $modelQuestion);

        $selectedModel = array_search($selectedLabel, $models);

        $config['provider'] = strtolower($provider);
        $config['model'] = $selectedModel;

        $aiService->saveConfig($config);

        $output->writeln('');
        $output->writeln("<info>✓ Modelo configurado: {$selectedLabel}</info>");
        $output->writeln('');

        return Command::SUCCESS;
    }

    private function loadConfig(): array
    {
        $home = getenv('USERPROFILE') ?: getenv('HOME');
        $configPath = $home . DIRECTORY_SEPARATOR . '.bedrock' . DIRECTORY_SEPARATOR . 'ai_config.json';
        
        if (!file_exists($configPath)) {
            return [];
        }

        return json_decode(file_get_contents($configPath), true) ?? [];
    }
}
