<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Cursor;

class OptionsCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('options')
             ->setDescription('Gestión de opciones de WordPress (wp_options)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>    Gestión de Opciones WP       </> <fg=cyan;options=bold>║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');

            $choices = [
                1 => '<fg=green>Exportar</> - Extraer opciones a JSON',
                2 => '<fg=green>Importar</> - Inyectar opciones desde JSON',
                3 => '<fg=cyan>Listar</> - Ver archivos JSON disponibles',
                4 => '<fg=yellow>Gestionar</> - Importar/Exportar opción específica',
                0 => '<fg=red>Volver</>',
            ];

            $question = new ChoiceQuestion('<fg=yellow>Selecciona una opción:</>', $choices, 1);
            $question->setAutocompleterValues(null);
            $question->setErrorMessage('<fg=red>Opción %s inválida.</>');

            $choice = $helper->ask($input, $output, $question);
            $cursor = new Cursor($output);
            $cursor->moveUp(1);
            $cursor->clearLine();
            $selectedIndex = array_search($choice, $choices);
            
            if ($selectedIndex === 0) {
                return Command::SUCCESS;
            }

            $commandMap = [
                1 => 'options:pull',
                2 => 'options:push',
                3 => 'options:list',
                4 => 'options:manage',
            ];

            $commandName = $commandMap[$selectedIndex];
            if ($commandName) {
                $output->writeln('');
                $command = $this->getApplication()->find($commandName);
                $command->run($input, $output);
            }
        }

        return Command::SUCCESS;
    }
}
