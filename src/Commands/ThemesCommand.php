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

class ThemesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('themes')
            ->setDescription('Gestión de temas');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $docker = new DockerService();
        $wpcli = new WpCliService($docker);
        
        while (true) {
            $choices = [
                1 => '<fg=green>Listar</> temas',
                2 => '<fg=green>Activar</> tema',
                3 => '<fg=green>Eliminar</> tema',
                4 => '<fg=green>Actualizar</> temas',
                5 => '<fg=green>Descomprimir</> ZIPs',
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
                    $this->listThemes($wpcli, $output);
                    break;
                case 2:
                    $theme = $helper->ask($input, $output, new Question('<fg=yellow>Tema a activar:</>'));
                    $this->activate($wpcli, $output, $theme);
                    break;
                case 3:
                    $theme = $helper->ask($input, $output, new Question('<fg=yellow>Tema a eliminar:</>'));
                    $this->delete($wpcli, $output, $theme);
                    break;
                case 4:
                    $this->update($wpcli, $output);
                    break;
                case 5:
                    $output->writeln('<comment>Función pendiente de implementación</comment>');
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }

    private function listThemes(WpCliService $wpcli, OutputInterface $output): int
    {
        $process = $wpcli->themeList();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }

    private function activate(WpCliService $wpcli, OutputInterface $output, string $theme): int
    {
        $output->writeln("<info>Activando {$theme}...</info>");
        $process = $wpcli->themeActivate($theme);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Tema activado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function delete(WpCliService $wpcli, OutputInterface $output, string $theme): int
    {
        $output->writeln("<info>Eliminando {$theme}...</info>");
        $process = $wpcli->themeDelete($theme);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Tema eliminado</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }

    private function update(WpCliService $wpcli, OutputInterface $output): int
    {
        $output->writeln('<info>Actualizando todos los temas...</info>');
        $process = $wpcli->themeUpdate();
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
        
        if ($process->isSuccessful()) {
            $output->writeln('<info>✓ Temas actualizados</info>');
            return Command::SUCCESS;
        }
        return Command::FAILURE;
    }
}
