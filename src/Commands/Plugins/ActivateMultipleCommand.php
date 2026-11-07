<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Roots\BedrockCli\Services\PluginActivationService;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class ActivateMultipleCommand extends Command
{
    use ProjectSelectorTrait;
    
    private PluginActivationService $pluginService;

    public function __construct(PluginActivationService $pluginService)
    {
        parent::__construct();
        $this->pluginService = $pluginService;
    }

    protected function configure(): void
    {
        $this->setName('plugins:activate-multiple')
             ->setDescription('Activar múltiples plugins por número en orden específico');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->ensureBedrockProject($input, $output)) {
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $projectRoot = getcwd();
        
        $plugins = $this->pluginService->getAvailablePlugins($projectRoot);
        
        if (empty($plugins)) {
            $output->writeln('<comment>No hay plugins disponibles</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>Plugins disponibles:</>');
        foreach ($plugins as $idx => $p) {
            $output->writeln('  ' . ($idx + 1) . '. ' . $p);
        }
        $output->writeln('');

        $question = new Question('<fg=yellow>Ingresa números separados por coma (ej: 1,3,5,7 o 1-5,7):</> ');
        $answer = $helper->ask($input, $output, $question);

        if (empty($answer)) {
            $output->writeln('<comment>Operación cancelada</comment>');
            return Command::SUCCESS;
        }

        $resolvedPlugins = $this->pluginService->parsePluginSelection($answer, $plugins);

        if (empty($resolvedPlugins)) {
            $output->writeln('<error>No se seleccionaron plugins válidos</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Activando plugins en orden...</info>');
        $output->writeln('');

        $this->pluginService->activateMultiple($resolvedPlugins, $output);

        $output->writeln('');
        $output->writeln('<fg=green;options=bold>✓ Proceso completado</>');

        return Command::SUCCESS;
    }
}
