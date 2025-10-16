<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Cursor;

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
        
        while (true) {
            $choices = [
                1 => '<fg=green>Listar</> plugins',
                2 => '<fg=green>Descomprimir</> ZIPs',
                3 => '<fg=green>Activar</> plugin',
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
                    $output->writeln('<comment>Función pendiente de implementación</comment>');
                    break;
                case 2:
                    $output->writeln('<comment>Función pendiente de implementación</comment>');
                    break;
                case 3:
                    $output->writeln('<comment>Función pendiente de implementación</comment>');
                    break;
            }
            
            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
