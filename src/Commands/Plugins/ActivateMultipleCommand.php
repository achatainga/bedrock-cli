<?php

namespace Roots\BedrockCli\Commands\Plugins;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Roots\BedrockCli\Services\WpCliService;
use Roots\BedrockCli\Traits\ProjectSelectorTrait;

class ActivateMultipleCommand extends Command
{
    use ProjectSelectorTrait;
    
    private WpCliService $wpCliService;

    public function __construct(WpCliService $wpCliService)
    {
        parent::__construct();
        $this->wpCliService = $wpCliService;
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
        $wpcli = $this->wpCliService;
        
        $projectRoot = getcwd();
        $pluginsDir = $projectRoot . '/web/app/plugins';

        if (!is_dir($pluginsDir)) {
            $output->writeln('<error>Carpeta de plugins no encontrada</error>');
            return Command::FAILURE;
        }

        $plugins = array_filter(scandir($pluginsDir), function($item) use ($pluginsDir) {
            return $item !== '.' && $item !== '..' && is_dir($pluginsDir . '/' . $item);
        });

        if (empty($plugins)) {
            $output->writeln('<comment>No hay plugins disponibles</comment>');
            return Command::SUCCESS;
        }

        $plugins = array_values($plugins);

        $output->writeln('');
        $output->writeln('<fg=cyan;options=bold>Plugins disponibles:</>')
;
        foreach ($plugins as $idx => $p) {
            $output->writeln('  ' . ($idx + 1) . '. ' . $p);
        }
        $output->writeln('');

        $question = new Question('<fg=yellow>Ingresa números separados por coma (ej: 1,3,5,7):</> ');
        $answer = $helper->ask($input, $output, $question);

        if (empty($answer)) {
            $output->writeln('<comment>Operación cancelada</comment>');
            return Command::SUCCESS;
        }

        $numbers = array_map('trim', explode(',', $answer));
        $resolvedPlugins = [];

        foreach ($numbers as $num) {
            if (!is_numeric($num)) continue;
            
            $index = (int)$num - 1;
            if (isset($plugins[$index])) {
                $resolvedPlugins[] = $plugins[$index];
            }
        }

        if (empty($resolvedPlugins)) {
            $output->writeln('<error>No se seleccionaron plugins válidos</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>Activando plugins en orden...</info>');
        $output->writeln('');

        foreach ($resolvedPlugins as $plugin) {
            $process = $wpcli->custom("plugin activate {$plugin}");
            $this->runWithLoader($process, $output, "Activando {$plugin}");

            if ($process->isSuccessful()) {
                $output->writeln("<info>✓ Plugin '{$plugin}' activado</info>");
            } else {
                $output->writeln("<error>✗ Error activando '{$plugin}'</error>");
            }
        }

        $output->writeln('');
        $output->writeln('<fg=green;options=bold>✓ Proceso completado</>')
;

        return Command::SUCCESS;
    }

    protected function runWithLoader(\Symfony\Component\Process\Process $process, OutputInterface $output, string $message): void
    {
        $frames = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        $frameIndex = 0;
        
        $process->start();
        
        while ($process->isRunning()) {
            $output->write("\r<comment>{$message}</comment> <fg=cyan>{$frames[$frameIndex]}</>");
            $frameIndex = ($frameIndex + 1) % count($frames);
            usleep(80000);
        }
        
        $output->write("\r<comment>{$message}</comment> <info>✓</info>\n");
    }
}
