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
            $output->writeln('<fg=cyan;options=bold>║</> <fg=yellow;options=bold>  BEDROCK CLI - Menú Principal  </> <fg=cyan;options=bold>     ║</>');
            $output->writeln('<fg=cyan;options=bold>╚═══════════════════════════════════════╝</>');
            $output->writeln('');

            $choices = [
                1 => '<fg=cyan>Info</>         - Estado del proyecto y tareas pendientes',
                2 => '<fg=green>Setup</>        - Configuración inicial',
                3 => '<fg=green>Docker</>       - Gestión de contenedores',
                4 => '<fg=green>Database</>     - Gestión de base de datos',
                5 => '<fg=green>Options</>      - Gestión de opciones WP',
                6 => '<fg=green>Install</>      - Instalar WordPress',
                7 => '<fg=green>Plugins</>      - Gestión de plugins',
                8 => '<fg=green>Themes</>       - Gestión de temas',
                9 => '<fg=green>Acorn</>        - Gestión de Roots Acorn',
                10 => '<fg=green>Backup</>       - Crear backup',
                11 => '<fg=red>Reinstall</>    - Reinstalar aplicación (DESTRUCTIVO)',
                12 => '<fg=cyan>Doctor</>       - Verificar dependencias del sistema',
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
                1 => 'info',
                2 => 'setup',
                3 => 'docker',
                4 => 'db',
                5 => 'options',
                6 => 'install',
                7 => 'plugins',
                8 => 'themes',
                9 => 'acorn',
                10 => 'backup',
                11 => 'reinstall',
                12 => 'doctor',
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
