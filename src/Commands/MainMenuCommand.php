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
        $this->setName('menu')
             ->setDescription('Abre el menú interactivo');
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
                1 => '<fg=green>Setup</>    - Configuración inicial',
                2 => '<fg=green>Docker</>   - Gestión de contenedores',
                3 => '<fg=green>Database</> - Gestión de base de datos',
                4 => '<fg=green>Options</>  - Gestión de opciones WP',
                5 => '<fg=green>Install</>  - Instalar WordPress',
                6 => '<fg=green>Plugins</>  - Gestión de plugins',
                7 => '<fg=green>Themes</>   - Gestión de temas',
                8 => '<fg=green>Backup</>   - Crear backup',
                9 => '<fg=red>Reinstall</>  - Reinstalar aplicación (DESTRUCTIVO)',
                10 => '<fg=cyan>Doctor</>   - Verificar dependencias del sistema',
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
                1 => 'setup',
                2 => 'docker',
                3 => 'db',
                4 => 'options',
                5 => 'install',
                6 => 'plugins',
                7 => 'themes',
                8 => 'backup',
                9 => 'reinstall',
                10 => 'doctor',
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
