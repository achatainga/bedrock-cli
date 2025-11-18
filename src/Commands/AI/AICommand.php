<?php

namespace Roots\BedrockCli\Commands\AI;

use Roots\BedrockCli\Services\AIContextBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class AICommand extends Command
{
    private AIContextBuilder $contextBuilder;

    public function __construct(AIContextBuilder $contextBuilder)
    {
        $this->contextBuilder = $contextBuilder;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('ai')
            ->setDescription('Interactúa con gemini-cli usando el contexto del proyecto.')
            ->addArgument('prompt', InputArgument::IS_ARRAY, 'El prompt para enviar a la IA.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $promptParts = $input->getArgument('prompt');
        if (empty($promptParts)) {
            $output->writeln('<error>Debes proporcionar un prompt. Ejemplo: bedrock ai "analiza este código"</error>');
            return Command::FAILURE;
        }
        $prompt = implode(' ', $promptParts);

        $output->writeln('<info>🤖 Inicializando Bedrock AI Copilot (wrapper para gemini-cli)...</info>');

        $context = $this->contextBuilder->buildContext();
        $contextFile = tempnam(sys_get_temp_dir(), 'GEMINI_CONTEXT') . '.md';
        $contextString = "## Contexto del Proyecto Bedrock\n\n```json\n" . json_encode($context, JSON_PRETTY_PRINT) . "\n```";
        file_put_contents($contextFile, $contextString);

        $projectName = basename(getcwd());
        $containerName = "{$projectName}_web";
        
        $command = [
            'docker', 'exec', '-i', $containerName,
            'gemini', '--context', $contextFile, $prompt,
        ];
        
        $output->writeln('<comment>  -> Ejecutando gemini-cli en Docker...</comment>');

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        unlink($contextFile);

        if (!$process->isSuccessful()) {
            $output->writeln('');
            $output->writeln('<error>Error ejecutando gemini-cli.</error>');
            $output->writeln('Asegúrate de que `gemini-cli` esté instalado en tu contenedor `web`.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}