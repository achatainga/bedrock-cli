<?php

namespace Roots\BedrockCli\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Cursor;

class MainMenuCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('main-menu')
             ->setDescription('Menú principal interactivo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        
        while (true) {
            $output->writeln('');
            $output->writeln('<fg=cyan;options=bold>╔═══════════════════════════════════════╗</>');
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  BEDROCK CLI - Menú Principal  </> <fg=cyan;options=bold>║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');

            $choices = [
                1 => '<fg=green>Docker</> - Gestión de contenedores',
                2 => '<fg=green>Database</> - Gestión de base de datos',
                3 => '<fg=green>Install</> - Instalar WordPress',
                4 => '<fg=green>Plugins</> - Gestión de plugins',
                5 => '<fg=green>Themes</> - Gestión de temas',
                6 => '<fg=green>Backup</> - Crear backup',
                7 => '<fg=green>Update</> - Actualizar sistema',
                0 => '<fg=red>Salir</>',
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
                $output->writeln('');
                $output->writeln('<info>Hasta luego!</info>');
                return Command::SUCCESS;
            }

            $commandMap = [
                1 => 'docker',
                2 => 'db',
                3 => 'install',
                4 => 'plugins',
                5 => 'themes',
                6 => 'backup',
                7 => 'update',
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
