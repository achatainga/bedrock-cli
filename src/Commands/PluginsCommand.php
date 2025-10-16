<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Cursor;
use Roots\BedrockCli\Services\DockerService;
use Roots\BedrockCli\Services\WpCliService;

class PluginsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('plugins')
            ->setDescription('Gestión de plugins');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        
        while (true) {
            $choices = [
                1 => '<fg=green>Listar</> plugins',
                2 => '<fg=green>Instalar</> plugin',
                3 => '<fg=green>Activar</> plugin',
                4 => '<fg=green>Desactivar</> plugin',
                5 => '<fg=green>Desinstalar</> plugin',
                6 => '<fg=green>Actualizar</> plugins',
                7 => '<fg=green>Descomprimir</> ZIPs',
                0 => '<fg=yellow>Volver atrás</>',
            ];
            
            $question = new ChoiceQuestion(
                '<fg=cyan>Selecciona una opción:</>',
                $choices,
                1
            );
            $question->setAutocompleterValues(null);

            $answer = $helper->ask($input, $output, $question);
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            
            $index = is_numeric($answer) ? (int)$answer : array_search($answer, $choices);
            
            if ($index === 0) {
                return Command::SUCCESS;
            }

            $output->writeln('');
            
            switch ($index) {
                case 1:
                    $this->listPlugins($wpcli, $output);
                    break;
                case 2:
                    $plugin = $helper->ask($input, $output, new Question('<fg=yellow>Slug del plugin:</>'));
                    $this->install($wpcli, $output, $plugin);
                    break;
                case 3:
                    $plugin = $helper->ask($input, $output, new Question('<fg=yellow>Plugin a activar:</>'));
                    $this->activate($wpcli, $output, $plugin);
                    break;
                case 4:
                    $plugin = $helper->ask($input, $output, new Question('<fg=yellow>Plugin a desactivar:</>'));
                    $this->deactivate($wpcli, $output, $plugin);
                    break;
                case 5:
                    $plugin = $helper->ask($input, $output, new Question('<fg=yellow>Plugin a desinstalar:</>'));
                    $this->uninstall($wpcli, $output, $plugin);
                    break;
                case 6:
                    $this->update($wpcli, $output);
                    break;
                case 7:
                    $output->writeln('<comment>Función pendiente de implementación</comment>');
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function listPlugins(WpCliService $wpcli, OutputInterface $output): int
    {
        $process = $wpcli->pluginList();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }

    private function install(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $output->writeln("<info>Instalando {$plugin}...</info>");
        $process = $wpcli->pluginInstall($plugin);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin instalado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function activate(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $output->writeln("<info>Activando {$plugin}...</info>");
        $process = $wpcli->pluginActivate($plugin);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin activado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function deactivate(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $output->writeln("<info>Desactivando {$plugin}...</info>");
        $process = $wpcli->pluginDeactivate($plugin);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin desactivado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function uninstall(WpCliService $wpcli, OutputInterface $output, string $plugin): int
    {
        $output->writeln("<info>Desinstalando {$plugin}...</info>");
        $process = $wpcli->pluginUninstall($plugin);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugin desinstalado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function update(WpCliService $wpcli, OutputInterface $output): int
    {
        $output->writeln('<info>Actualizando todos los plugins...</info>');
        $process = $wpcli->pluginUpdate();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Plugins actualizados</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }
}
